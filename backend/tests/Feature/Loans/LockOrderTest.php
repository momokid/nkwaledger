<?php

// Everything that touches a pending entry and its codes must lock the entry first and the codes second.
// Two requests that lock them in opposite order can wait for each other (a deadlock) and one fails.
// Needs PostgreSQL: SQLite takes no row locks.

require_once __DIR__ . '/../../Support/LoanWorld.php';

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('needs PostgreSQL');
    }

    buildLoanWorld($this);

    // for each top-level transaction in $run: the order in which it first took the entry and the code rows
    $this->lockOrders = function (callable $run): array {
        $base = DB::transactionLevel();
        $group = 0;
        $orders = [];

        DB::listen(function (QueryExecuted $query) use (&$group, &$orders) {
            $sql = strtolower($query->sql);
            $kind = match (true) {
                str_contains($sql, 'from "pending_entries"') && str_contains($sql, 'for update') => 'entry',
                str_contains($sql, '"pending_entry_codes"') && (str_contains($sql, 'for update') || str_starts_with($sql, 'update') || str_starts_with($sql, 'insert')) => 'code',
                default => null,
            };

            if ($kind !== null && ! in_array($kind, $orders[$group] ?? [], true)) {
                $orders[$group][] = $kind;
            }
        });
        Event::listen(TransactionBeginning::class, function (TransactionBeginning $event) use ($base, &$group) {
            if ($event->connection->transactionLevel() === $base + 1) {
                $group++;
            }
        });

        $run();

        return array_values(array_filter($orders, fn($order) => count($order) === 2));
    };
});

test('sending a code takes the entry before the code rows', function () {
    $entry = ($this->pendingMoney)();

    $orders = ($this->lockOrders)(fn() => $this->codes->send($entry, $this->agent));

    expect($orders)->toBe([['entry', 'code']]);
});

test('typing a code takes the entry before the code rows', function () {
    $entry = ($this->pendingMoney)();
    $this->codes->send($entry, $this->agent);
    $code = ($this->lastCode)();

    $orders = ($this->lockOrders)(fn() => $this->codes->enter($entry, $this->agent, $code));

    expect($orders)->toBe([['entry', 'code']]);
});

test('rejecting the waiting repayments of a cancelled loan takes the entry before the code rows', function () {
    $loan = ($this->openLoan)();
    $entry = $this->entries->recordRepayment($loan, $this->agent, ($this->repayForm)('600', []));
    $this->codes->send($entry, $this->agent);

    $orders = ($this->lockOrders)(fn() => $this->entries->rejectRepaymentsFor($loan, $this->agent));

    expect($orders)->toBe([['entry', 'code']]);
});

test('cancelling a loan takes the waiting repayment and its code before the loan row', function () {
    $loan = ($this->openLoan)();
    $entry = $this->entries->recordRepayment($loan, $this->agent, ($this->repayForm)('600', []));
    $this->codes->send($entry, $this->agent);

    $reversals = app(\App\Services\Ledger\ReversalService::class);
    $request = $reversals->request(\App\Models\Transaction::find($loan->transaction_id), $this->clerk, 'Typed wrongly');
    $seen = [];

    DB::listen(function (QueryExecuted $query) use (&$seen) {
        $sql = strtolower($query->sql);

        match (true) {
            str_contains($sql, 'from "pending_entries"') && str_contains($sql, 'for update') => $seen[] = 'entry',
            str_contains($sql, '"pending_entry_codes"') && str_starts_with($sql, 'update') => $seen[] = 'code',
            str_starts_with($sql, 'update "loans"') => $seen[] = 'loan',
            default => null,
        };
    });

    $reversals->approve($request, $this->approver);

    expect($seen)->toBe(['entry', 'code', 'loan']);
});
