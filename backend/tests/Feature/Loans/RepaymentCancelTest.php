<?php

// Task B2: cancelling a loan marks every pending repayment on it as rejected, in the same step. The agent and the
// farmer both see it as rejected, and the SMS code for it stops working.

require_once __DIR__ . '/../../Support/LoanWorld.php';

use App\Exceptions\Loans\EntryRefused;
use App\Models\AuditLog;
use App\Models\LoanRepayment;
use App\Models\PendingEntry;
use App\Models\PendingEntryCode;
use App\Models\Transaction;
use App\Services\Ledger\ReversalService;
use App\Services\Loans\EntryCodeService;

beforeEach(function () {
    buildLoanWorld($this);

    $this->loan = ($this->openLoan)();
    $this->pendingRepay = fn($loan, string $amount) => $this->entries->recordRepayment($loan, $this->agent, ($this->repayForm)($amount));

    $this->cancelLoan = function ($loan) {
        $service = app(ReversalService::class);
        $service->approve($service->request(Transaction::find($loan->transaction_id), $this->clerk, 'Typed wrongly'), $this->approver);
    };

    $this->smsCode = function () {
        $sent = app(\App\Contracts\SmsProvider::class)->sent;
        preg_match_all('/\b(\d{6})\b/', end($sent)['message'], $match);

        return end($match[1]);
    };
});

test('cancelling a loan rejects both of its pending repayments, and nothing posts', function () {
    $first = ($this->pendingRepay)($this->loan, '100');
    $second = ($this->pendingRepay)($this->loan, '200');
    $transactions = Transaction::count();

    ($this->cancelLoan)($this->loan);

    expect([$first->fresh()->status, $second->fresh()->status])->toBe(['rejected', 'rejected'])
        ->and((int) $first->fresh()->decided_by)->toBe($this->approver->id)
        ->and(LoanRepayment::count())->toBe(0)
        // only the cancellation itself was written: its correction record
        ->and(Transaction::count())->toBe($transactions + 1);

    $actions = AuditLog::whereIn('auditable_id', [$first->id, $second->id])->where('auditable_type', PendingEntry::class)->where('action', 'pending_entry.rejected')->count();

    expect($actions)->toBe(2);
});

test('only that loan\'s pending repayments are touched', function () {
    $other = ($this->openLoan)(['lender_name' => 'Another Lender']);
    $mine = ($this->pendingRepay)($this->loan, '100');
    $theirs = ($this->pendingRepay)($other, '100');
    $done = ($this->pendingRepay)($this->loan, '50');
    $this->entries->decline($done, $this->farmerUser);

    ($this->cancelLoan)($this->loan);

    expect([$mine->fresh()->status, $theirs->fresh()->status, $done->fresh()->status])->toBe(['rejected', 'pending', 'declined']);
});

test('the SMS code stops working, and so does accepting in the app', function () {
    $entry = ($this->pendingRepay)($this->loan, '100');
    app(EntryCodeService::class)->send($entry, $this->agent);
    $code = ($this->smsCode)();

    ($this->cancelLoan)($this->loan);

    expect(PendingEntryCode::where('pending_entry_id', $entry->id)->whereNull('cancelled_at')->count())->toBe(0)
        ->and(fn() => app(EntryCodeService::class)->enter($entry, $this->agent, $code))->toThrow(EntryRefused::class)
        ->and(fn() => $this->entries->accept($entry, $this->farmerUser, 'app'))->toThrow(EntryRefused::class)
        ->and(LoanRepayment::count())->toBe(0)
        ->and($entry->fresh()->status)->toBe('rejected');
});

test('the agent sees both as rejected on the farmer\'s page', function () {
    ($this->pendingRepay)($this->loan, '100');
    ($this->pendingRepay)($this->loan, '200');

    ($this->cancelLoan)($this->loan);

    $rows = null;
    $this->actingAs($this->agent)->get("/agent/farmers/{$this->profile->uuid}")->assertOk()->assertInertia(function ($page) use (&$rows) {
        $rows = $page->toArray()['props']['loans'];
    });

    $entries = collect($rows)->where('row', 'entry');

    expect($entries)->toHaveCount(2)->and($entries->pluck('status')->unique()->all())->toBe(['rejected']);
});

test('the farmer sees both as rejected on the loan page, and no longer as waiting', function () {
    ($this->pendingRepay)($this->loan, '100');
    ($this->pendingRepay)($this->loan, '200');

    ($this->cancelLoan)($this->loan);

    $props = null;
    $this->actingAs($this->farmerUser)->get("/loans/{$this->loan->uuid}")->assertOk()->assertInertia(function ($page) use (&$props) {
        $props = $page->toArray()['props'];
    });

    $this->actingAs($this->farmerUser)->get('/loans')->assertInertia(fn($page) => $page->where('pending', []));

    expect($props['rejected'])->toHaveCount(2)
        ->and(collect($props['rejected'])->pluck('amount')->sort()->values()->all())->toBe([10000, 20000])
        ->and(array_keys($props['rejected'][0]))->toEqualCanonicalizing(['uuid', 'date', 'amount']);
});

test('cancelling a loan with nothing waiting works as before', function () {
    ($this->cancelLoan)($this->loan);

    expect($this->loan->fresh()->status)->toBe('cancelled');
});
