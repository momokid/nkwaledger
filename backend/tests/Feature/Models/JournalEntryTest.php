<?php

use App\Models\AccountingPeriod;
use App\Models\FarmerProfile;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\LedgerAccount;
use App\Models\LedgerCategory;
use App\Models\LedgerClass;
use App\Models\LedgerControl;
use App\Models\LedgerSubcategory;
use App\Models\LedgerType;
use App\Models\Transaction;
use App\Models\TransactionTemplate;
use App\Models\User;
use Illuminate\Database\QueryException;

beforeEach(function () {
    $drClass = LedgerClass::create(['name' => 'Dr']);
    $crClass = LedgerClass::create(['name' => 'Cr']);

    $assets = LedgerCategory::create(['name' => 'Assets', 'class_id' => $drClass->id]);
    $income = LedgerCategory::create(['name' => 'Income', 'class_id' => $crClass->id]);

    $assetSub = LedgerSubcategory::create(['category_id' => $assets->id, 'name' => 'Short Term Asset']);
    $incomeSub = LedgerSubcategory::create(['category_id' => $income->id, 'name' => 'Farm Income']);

    $control = LedgerControl::create(['name' => 'Cash Ctrl']);
    $type = LedgerType::create(['name' => 'GL']);

    $this->cash = LedgerAccount::create([
        'name' => 'Cash on Hand',
        'control_id' => $control->id,
        'subcategory_id' => $assetSub->id,
        'type_id' => $type->id,
    ]);

    $this->sales = LedgerAccount::create([
        'name' => 'Crop Sales',
        'control_id' => $control->id,
        'subcategory_id' => $incomeSub->id,
        'type_id' => $type->id,
    ]);

    $template = TransactionTemplate::create([
        'name' => 'I sold crops',
        'slug' => 'crop_sale',
        'transaction_type' => 'INCOME',
        'debit_account_id' => $this->cash->id,
        'credit_account_id' => $this->sales->id,
        'settlement_side' => 'debit',
    ]);

    $period = AccountingPeriod::create([
        'name' => 'Test Period',
        'starts_on' => now()->startOfYear()->toDateString(),
        'ends_on' => now()->endOfYear()->toDateString(),
    ]);

    $this->transaction = Transaction::create([
        'farmer_profile_id' => FarmerProfile::factory()->create()->id,
        'transaction_template_id' => $template->id,
        'transaction_type' => 'INCOME',
        'accounting_period_id' => $period->id,
        'transaction_date' => now()->toDateString(),
        'amount_minor' => 25000,
        'settlement_account_id' => $this->cash->id,
        'channel' => 'web',
        'recorded_by' => User::factory()->create()->id,
        'posted_at' => now(),
    ]);

    $this->line = function (JournalEntry $entry, int $number, int $debit, int $credit, ?int $account = null) {
        return JournalLine::create([
            'journal_entry_id' => $entry->id,
            'ledger_account_id' => $account ?? $this->cash->id,
            'debit_minor' => $debit,
            'credit_minor' => $credit,
            'line_number' => $number,
        ]);
    };
});

it('records an entry against a transaction', function () {
    $entry = JournalEntry::create([
        'transaction_id' => $this->transaction->id,
        'narration' => 'Sold maize at market',
        'posted_at' => now(),
    ]);

    expect($entry->narration)->toBe('Sold maize at market');
    expect($entry->transaction->id)->toBe($this->transaction->id);
});

it('holds its lines in order', function () {
    $entry = JournalEntry::create([
        'transaction_id' => $this->transaction->id,
        'posted_at' => now(),
    ]);

    ($this->line)($entry, 2, 0, 25000, $this->sales->id);
    ($this->line)($entry, 1, 25000, 0);

    expect($entry->lines()->pluck('line_number')->all())->toBe([1, 2]);
});

// one transaction, one entry, or the books say the same thing twice
it('refuses a second entry for the same transaction', function () {
    JournalEntry::create([
        'transaction_id' => $this->transaction->id,
        'posted_at' => now(),
    ]);

    expect(fn() => JournalEntry::create([
        'transaction_id' => $this->transaction->id,
        'posted_at' => now(),
    ]))->toThrow(QueryException::class);
});

// this is the promise the whole product rests on
it('is balanced when debits equal credits', function () {
    $entry = JournalEntry::create([
        'transaction_id' => $this->transaction->id,
        'posted_at' => now(),
    ]);

    ($this->line)($entry, 1, 25000, 0);
    ($this->line)($entry, 2, 0, 25000, $this->sales->id);

    expect($entry->isBalanced())->toBeTrue();
});

it('is not balanced when the sides differ', function () {
    $entry = JournalEntry::create([
        'transaction_id' => $this->transaction->id,
        'posted_at' => now(),
    ]);

    ($this->line)($entry, 1, 25000, 0);
    ($this->line)($entry, 2, 0, 20000, $this->sales->id);

    expect($entry->isBalanced())->toBeFalse();
});

// an entry with nothing in it balances at zero, which is not a real entry
it('is not balanced when it has no lines at all', function () {
    $entry = JournalEntry::create([
        'transaction_id' => $this->transaction->id,
        'posted_at' => now(),
    ]);

    expect($entry->isBalanced())->toBeFalse();
});

it('is not balanced with only one line', function () {
    $entry = JournalEntry::create([
        'transaction_id' => $this->transaction->id,
        'posted_at' => now(),
    ]);

    ($this->line)($entry, 1, 25000, 0);

    expect($entry->isBalanced())->toBeFalse();
});

it('balances across more than two lines', function () {
    $entry = JournalEntry::create([
        'transaction_id' => $this->transaction->id,
        'posted_at' => now(),
    ]);

    ($this->line)($entry, 1, 15000, 0);
    ($this->line)($entry, 2, 10000, 0);
    ($this->line)($entry, 3, 0, 25000, $this->sales->id);

    expect($entry->isBalanced())->toBeTrue();
});

// the service calls this inside a database transaction, so a bad entry never survives
it('throws when asked to prove a balance it does not have', function () {
    $entry = JournalEntry::create([
        'transaction_id' => $this->transaction->id,
        'posted_at' => now(),
    ]);

    ($this->line)($entry, 1, 25000, 0);

    expect(fn() => $entry->assertBalanced())->toThrow(RuntimeException::class);
});

it('says nothing when the balance is good', function () {
    $entry = JournalEntry::create([
        'transaction_id' => $this->transaction->id,
        'posted_at' => now(),
    ]);

    ($this->line)($entry, 1, 25000, 0);
    ($this->line)($entry, 2, 0, 25000, $this->sales->id);

    expect($entry->assertBalanced())->toBeNull();
});

it('adds up each side', function () {
    $entry = JournalEntry::create([
        'transaction_id' => $this->transaction->id,
        'posted_at' => now(),
    ]);

    ($this->line)($entry, 1, 25000, 0);
    ($this->line)($entry, 2, 0, 25000, $this->sales->id);

    expect($entry->totalDebitMinor())->toBe(25000);
    expect($entry->totalCreditMinor())->toBe(25000);
});

// a book you can edit is not a book
it('cannot be changed once written', function () {
    $entry = JournalEntry::create([
        'transaction_id' => $this->transaction->id,
        'posted_at' => now(),
    ]);

    expect(fn() => $entry->update(['narration' => 'something else']))
        ->toThrow(RuntimeException::class);
});

it('cannot be deleted', function () {
    $entry = JournalEntry::create([
        'transaction_id' => $this->transaction->id,
        'posted_at' => now(),
    ]);

    expect(fn() => $entry->delete())->toThrow(RuntimeException::class);
});

it('needs a transaction to belong to', function () {
    expect(fn() => JournalEntry::create(['posted_at' => now()]))
        ->toThrow(QueryException::class);
});
