<?php

// Task 3.6b, part A: an agent's loan waits as a pending entry. It never touches the books, a balance, a
// dashboard or a report until the farmer accepts it, in the app or with the code.

require_once __DIR__ . '/../../Support/LoanWorld.php';

use App\Exceptions\Ledger\PostingFailed;
use App\Exceptions\Loans\EntryRefused;
use App\Models\AccountingPeriod;
use App\Models\AuditLog;
use App\Models\JournalEntry;
use App\Models\Loan;
use App\Models\Notification;
use App\Models\PendingEntry;
use App\Models\Transaction;

beforeEach(function () {
    buildLoanWorld($this);
});

test('recording stores a pending entry with integer pesewas and touches nothing else', function () {
    $transactions = Transaction::count();
    $journals = JournalEntry::count();

    $entry = ($this->pendingMoney)(['interest' => '50', 'due_date' => now()->addDays(30)->toDateString(), 'note' => 'seed money']);

    expect($entry->status)->toBe('pending')
        ->and($entry->entry_type)->toBe('loan.money')
        ->and($entry->uuid)->not->toBeEmpty()
        ->and((int) $entry->recorded_by)->toBe($this->agent->id)
        ->and((int) $entry->farmer_profile_id)->toBe($this->profile->id)
        ->and($entry->payload['principal_minor'])->toBe(50000)
        ->and($entry->payload['agreed_interest_minor'])->toBe(5000)
        ->and($entry->payload['settlement_account_id'])->toBe($this->cash->id)
        ->and([Loan::count(), Transaction::count(), JournalEntry::count()])->toBe([0, $transactions, $journals]);
});

test('an existing loan entry is another entry type with no account', function () {
    $entry = ($this->pendingExisting)();

    expect([$entry->entry_type, $entry->payload['kind'], $entry->payload['principal_minor'], $entry->payload['settlement_account_id']])
        ->toBe(['loan.existing', 'existing', 30000, null]);
});

test('recording checks the same rules as the farmer form, and stores nothing when one fails', function () {
    expect(fn() => ($this->pendingMoney)(['amount' => '0']))->toThrow(PostingFailed::class, 'Enter an amount above zero.')
        ->and(fn() => ($this->pendingMoney)(['date' => now()->addDay()->toDateString()]))->toThrow(PostingFailed::class, 'This date is in the future.')
        ->and(fn() => ($this->pendingMoney)(['paid_into' => $this->receivable->id]))->toThrow(InvalidArgumentException::class)
        ->and(fn() => ($this->pendingMoney)(['lender' => '']))->toThrow(PostingFailed::class, 'lender.not_in_list')
        ->and(PendingEntry::count())->toBe(0);
});

test('recording is audit-logged with the agent, and the farmer is told with the approved words', function () {
    $entry = ($this->pendingMoney)();

    $audit = AuditLog::where('auditable_type', PendingEntry::class)->where('auditable_id', $entry->id)->first();
    $note = Notification::where('user_id', $this->farmerUser->id)->first();

    expect($audit->action)->toBe('pending_entry.recorded')
        ->and($note->message)->toBe('Your agent recorded a loan for you. It is not counted until you accept it.')
        ->and($note->link)->toBe('/loans');
});

test('a pending entry changes no figure: the books, the dashboard, My Records, the reports and the loans list', function () {
    $before = figureSnapshot($this, withLoanFigures: true);

    ($this->pendingMoney)();
    ($this->pendingExisting)();
    $second = ($this->pendingMoney)(['amount' => '90']);
    $this->entries->withdraw($second, $this->agent);

    expect(figureSnapshot($this, withLoanFigures: true))->toBe($before);
});

test('a pending entry never expires', function () {
    $entry = ($this->pendingMoney)();

    $this->travel(400)->days();

    expect($entry->fresh()->status)->toBe('pending');
});

test('entries are never deleted', function () {
    $entry = ($this->pendingMoney)();

    expect(fn() => $entry->delete())->toThrow(RuntimeException::class);
});

// --- accepting in the app ---

test('accepting makes the loan, posts it, links the entry, and records the agent and the way', function () {
    $entry = ($this->pendingMoney)(['date' => now()->subDays(3)->toDateString()]);

    $loan = $this->entries->accept($entry, $this->farmerUser, 'app');
    $entry->refresh();

    expect($loan->kind)->toBe('money')
        ->and($loan->principal_minor)->toBe(50000)
        ->and($loan->balanceMinor())->toBe(50000)
        ->and($loan->loan_date->toDateString())->toBe(now()->subDays(3)->toDateString())
        ->and((int) $loan->recorded_by)->toBe($this->agent->id)
        ->and($loan->accepted_via)->toBe('app')
        ->and($loan->transaction->transaction_type)->toBe('LOAN')
        ->and($entry->status)->toBe('accepted')
        ->and([(int) $entry->decided_by, $entry->decided_via])->toBe([$this->farmerUser->id, 'app'])
        ->and([$entry->result_type, (int) $entry->result_id])->toBe(['loan', $loan->id]);
});

test('an existing loan entry is dated on the day it is accepted', function () {
    $entry = ($this->pendingExisting)();

    $this->travel(5)->days();
    $loan = $this->entries->accept($entry, $this->farmerUser, 'app');

    expect($loan->loan_date->toDateString())->toBe(now()->toDateString())
        ->and($loan->transaction->transaction_date->toDateString())->toBe(now()->toDateString());
});

test('accepting the same entry twice does nothing the second time', function () {
    $entry = ($this->pendingMoney)();

    $first = $this->entries->accept($entry, $this->farmerUser, 'app');
    $second = $this->entries->accept($entry->fresh(), $this->farmerUser, 'app');

    expect($second->id)->toBe($first->id)
        ->and(Loan::count())->toBe(1)
        ->and(Transaction::where('transaction_type', 'LOAN')->count())->toBe(1);
});

test('every check runs again at acceptance: a closed period leaves the entry pending with the existing text', function () {
    $entry = ($this->pendingMoney)(['date' => now()->subDays(2)->toDateString()]);
    AccountingPeriod::first()->close($this->admin);

    expect(fn() => $this->entries->accept($entry, $this->farmerUser, 'app'))
        ->toThrow(PostingFailed::class, 'That period is closed. Record it against today instead.');

    expect([$entry->fresh()->status, Loan::count(), Transaction::where('transaction_type', 'LOAN')->count()])->toBe(['pending', 0, 0]);
});

test('acceptance is audit-logged with the farmer and the way, and the agent is told', function () {
    $entry = ($this->pendingMoney)();
    $this->entries->accept($entry, $this->farmerUser, 'app');

    $audit = AuditLog::where('auditable_type', PendingEntry::class)->where('auditable_id', $entry->id)->get()->sortBy('id')->values();
    $loanAudit = AuditLog::where('auditable_type', Loan::class)->first();

    expect($audit->pluck('action')->all())->toBe(['pending_entry.recorded', 'pending_entry.accepted'])
        ->and((int) $audit[1]->user_id)->toBe($this->farmerUser->id)
        ->and($audit[1]->new_values['via'])->toBe('app')
        ->and($loanAudit->action)->toBe('loan.created')
        ->and(Notification::where('user_id', $this->agent->id)->first()->message)
        ->toBe($this->profile->user->surname . ' ' . $this->profile->user->first_name . ' accepted the loan.');
});

// --- declining and withdrawing ---

test('declining ends the entry, creates nothing, is audit-logged and tells the agent', function () {
    $entry = ($this->pendingMoney)();
    $this->entries->decline($entry, $this->farmerUser);

    $audit = AuditLog::where('auditable_type', PendingEntry::class)->where('auditable_id', $entry->id)->get()->sortBy('id')->pluck('action')->values()->all();

    expect([$entry->fresh()->status, Loan::count()])->toBe(['declined', 0])
        ->and($audit)->toBe(['pending_entry.recorded', 'pending_entry.declined'])
        ->and(Notification::where('user_id', $this->agent->id)->first()->message)
        ->toBe($this->profile->user->surname . ' ' . $this->profile->user->first_name . ' declined the loan.');
});

test('withdrawing ends the entry, is audit-logged, and the farmer can no longer accept it', function () {
    $entry = ($this->pendingMoney)();
    $this->entries->withdraw($entry, $this->agent);

    $audit = AuditLog::where('auditable_type', PendingEntry::class)->where('auditable_id', $entry->id)->get()->sortBy('id')->pluck('action')->values()->all();

    expect($entry->fresh()->status)->toBe('withdrawn')
        ->and($audit)->toBe(['pending_entry.recorded', 'pending_entry.withdrawn'])
        ->and(fn() => $this->entries->accept($entry->fresh(), $this->farmerUser, 'app'))->toThrow(EntryRefused::class)
        ->and(Loan::count())->toBe(0);
});

test('an entry that is no longer pending cannot be declined, withdrawn or accepted again', function () {
    $declined = ($this->pendingMoney)();
    $this->entries->decline($declined, $this->farmerUser);

    expect(fn() => $this->entries->accept($declined->fresh(), $this->farmerUser, 'app'))->toThrow(EntryRefused::class)
        ->and(fn() => $this->entries->withdraw($declined->fresh(), $this->agent))->toThrow(EntryRefused::class)
        ->and(fn() => $this->entries->decline($declined->fresh(), $this->farmerUser))->toThrow(EntryRefused::class);
});
