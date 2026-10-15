<?php

// Task 3.7: an agent's repayment waits as a pending entry and touches nothing until the farmer accepts it.
// If the loan moved on since the agent typed it, the entry is rejected, and an extra amount can only be
// confirmed by the farmer in the app, never by the SMS code.

require_once __DIR__ . '/../../Support/LoanWorld.php';

use App\Support\Wording;
use App\Exceptions\Ledger\PostingFailed;
use App\Exceptions\Loans\EntryRefused;
use App\Models\AuditLog;
use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\PendingEntry;
use App\Models\Transaction;
use App\Services\Loans\RepaymentService;

beforeEach(function () {
    buildLoanWorld($this);
    $this->loan = ($this->openLoan)();

    $this->pendingRepay = fn(string $amount, array $over = []) => $this->entries->recordRepayment($this->loan, $this->agent, ($this->repayForm)($amount, $over));

    $this->smsCode = function () {
        $sent = app(\App\Contracts\SmsProvider::class)->sent;
        preg_match_all('/\b(\d{6})\b/', end($sent)['message'], $match);

        return end($match[1]);
    };
});

test('recording stores a pending entry with a snapshot of the loan and touches nothing else', function () {
    $transactions = Transaction::count();

    $entry = ($this->pendingRepay)('600');

    expect($entry->entry_type)->toBe('loan.repayment')
        ->and($entry->status)->toBe('pending')
        ->and($entry->payload['amount_minor'])->toBe(60000)
        ->and($entry->payload['snapshot'])->toBe(['remaining_principal' => 100000, 'remaining_interest' => 20000])
        ->and($entry->payload['excess'])->toBeFalse()
        ->and([Transaction::count(), LoanRepayment::count()])->toBe([$transactions, 0])
        ->and($this->loan->fresh()->balanceMinor())->toBe(120000);

    $log = AuditLog::where('auditable_type', PendingEntry::class)->where('auditable_id', $entry->id)->first();

    expect($log->action)->toBe('pending_entry.recorded');
});

test('recording checks the same rules as the farmer and stores nothing when one fails', function () {
    expect(fn() => ($this->pendingRepay)('0'))->toThrow(PostingFailed::class, 'Enter an amount above zero.')
        ->and(fn() => ($this->pendingRepay)('10', ['date' => now()->addDay()->toDateString()]))->toThrow(PostingFailed::class, 'This date is in the future.')
        ->and(fn() => ($this->pendingRepay)('10', ['paid_from' => $this->receivable->id]))->toThrow(PostingFailed::class)
        ->and(PendingEntry::count())->toBe(0);
});

test('accepting in the app posts the repayment, linked to the agent who recorded it', function () {
    $entry = ($this->pendingRepay)('600');

    $repayment = $this->entries->accept($entry, $this->farmerUser, 'app');

    expect($repayment)->toBeInstanceOf(LoanRepayment::class)
        ->and([$repayment->principal_minor, $repayment->interest_minor, $repayment->accepted_via])->toBe([50000, 10000, 'app'])
        ->and((int) $repayment->recorded_by)->toBe($this->agent->id)
        ->and($entry->fresh()->status)->toBe('accepted')
        ->and($entry->fresh()->result_type)->toBe('loan_repayment')
        ->and($this->loan->fresh()->balanceMinor())->toBe(60000);

    $actions = AuditLog::where('auditable_type', PendingEntry::class)->where('auditable_id', $entry->id)->orderBy('id')->pluck('action')->all();

    expect($actions)->toBe(['pending_entry.recorded', 'pending_entry.accepted'])
        ->and(AuditLog::where('action', 'loan_repayment.created')->exists())->toBeTrue();
});

test('accepting with the SMS code posts the repayment', function () {
    $entry = ($this->pendingRepay)('600');

    $this->codes->send($entry, $this->agent);
    $repayment = $this->codes->enter($entry, $this->agent, ($this->smsCode)());

    expect($repayment)->toBeInstanceOf(LoanRepayment::class)
        ->and($repayment->accepted_via)->toBe('code')
        ->and($this->loan->fresh()->balanceMinor())->toBe(60000);
});

test('if the loan changed since the agent typed it, the entry is rejected and nothing posts', function () {
    $entry = ($this->pendingRepay)('600');

    ($this->repay)($this->loan, '100');
    $transactions = Transaction::count();

    $result = $this->entries->accept($entry, $this->farmerUser, 'app');

    expect($result)->toBeNull()
        ->and($entry->fresh()->status)->toBe('rejected')
        ->and(Transaction::count())->toBe($transactions)
        ->and(LoanRepayment::count())->toBe(1)
        ->and(AuditLog::where('auditable_id', $entry->id)->where('action', 'pending_entry.rejected')->exists())->toBeTrue();

    expect(fn() => $this->entries->accept($entry, $this->farmerUser, 'app'))->toThrow(EntryRefused::class);
});

test('the snapshot is also checked on the code path', function () {
    $entry = ($this->pendingRepay)('600');
    $this->codes->send($entry, $this->agent);
    ($this->repay)($this->loan, '100');

    $result = $this->codes->enter($entry, $this->agent, ($this->smsCode)());

    expect($result)->toBeNull()->and($entry->fresh()->status)->toBe('rejected');
});

test('an amount above what is owed is flagged, and the SMS code is refused for it', function () {
    $entry = ($this->pendingRepay)('1300');

    expect($entry->payload['excess'])->toBeTrue();

    expect(fn() => $this->codes->send($entry, $this->agent))->toThrow(EntryRefused::class, Wording::get(RepaymentService::CODE_NOT_ALLOWED));

    // even with a code in hand, the server refuses on entry
    \App\Models\PendingEntryCode::create([
        'pending_entry_id' => $entry->id,
        'code_hash' => hash_hmac('sha256', $entry->uuid . '|123456', (string) config('app.key')),
        'expires_at' => now()->addMinutes(5),
        'created_at' => now(),
    ]);

    expect(fn() => $this->codes->enter($entry, $this->agent, '123456'))->toThrow(EntryRefused::class, Wording::get(RepaymentService::CODE_NOT_ALLOWED))
        ->and($entry->fresh()->status)->toBe('pending')
        ->and(LoanRepayment::count())->toBe(0)
        ->and(Transaction::where('transaction_type', 'LOAN')->count())->toBe(1);
});

test('an amount above what is owed is accepted in the app only with the farmer confirming', function () {
    $entry = ($this->pendingRepay)('1300');

    expect(fn() => $this->entries->accept($entry, $this->farmerUser, 'app'))->toThrow(PostingFailed::class, Wording::get(RepaymentService::EXCESS_NOT_CONFIRMED))
        ->and($entry->fresh()->status)->toBe('pending');

    $repayment = $this->entries->accept($entry, $this->farmerUser, 'app', true);

    expect([$repayment->principal_minor, $repayment->interest_minor, $repayment->excess_confirmed])->toBe([100000, 30000, true])
        ->and($entry->fresh()->status)->toBe('accepted');
});

test('a forged confirmation on an entry with no excess is refused', function () {
    $entry = ($this->pendingRepay)('600');

    expect(fn() => $this->entries->accept($entry, $this->farmerUser, 'app', true))->toThrow(PostingFailed::class, Wording::get(RepaymentService::CONFIRMATION_NOT_NEEDED))
        ->and($entry->fresh()->status)->toBe('pending');
});

test('a loan cancelled in the meantime cannot take the repayment', function () {
    $entry = ($this->pendingRepay)('600');
    $this->loan->forceFill(['status' => Loan::CANCELLED])->save();

    expect(fn() => $this->entries->accept($entry, $this->farmerUser, 'app'))->toThrow(PostingFailed::class)
        ->and(LoanRepayment::count())->toBe(0);
});

test('the farmer can decline and the agent can withdraw, and neither touches the books', function () {
    $declined = ($this->pendingRepay)('100');
    $withdrawn = ($this->pendingRepay)('200');

    $this->entries->decline($declined, $this->farmerUser);
    $this->entries->withdraw($withdrawn, $this->agent);

    expect([$declined->fresh()->status, $withdrawn->fresh()->status])->toBe(['declined', 'withdrawn'])
        ->and(LoanRepayment::count())->toBe(0);
});

test('two acceptances at once make one repayment', function () {
    $entry = ($this->pendingRepay)('600');

    $first = $this->entries->accept($entry, $this->farmerUser, 'app');
    $second = $this->entries->accept($entry, $this->farmerUser, 'app');

    expect($second->id)->toBe($first->id)->and(LoanRepayment::count())->toBe(1);
});
