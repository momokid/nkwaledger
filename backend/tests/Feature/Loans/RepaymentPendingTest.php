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

test('an agent cannot record an amount above what is owed, so there is never an extra amount to refuse', function () {
    expect(fn() => ($this->pendingRepay)('1300'))->toThrow(PostingFailed::class, Wording::get(RepaymentService::OVER_TOTAL))
        ->and(PendingEntry::count())->toBe(0);
});

test('an entry for exactly what is owed can be accepted in the app and by the SMS code alike', function () {
    $entry = ($this->pendingRepay)('1200');
    $other = ($this->pendingRepay)('1200');

    $this->codes->send($other, $this->agent);

    $byApp = $this->entries->accept($entry, $this->farmerUser, 'app');

    expect([$byApp->principal_minor, $byApp->interest_minor])->toBe([100000, 20000])
        ->and($entry->fresh()->status)->toBe('accepted')
        ->and($this->codes->enter($other, $this->agent, ($this->smsCode)()))->toBeNull()
        ->and($other->fresh()->status)->toBe('rejected');
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
