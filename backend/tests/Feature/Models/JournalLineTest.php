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

    $this->profile = FarmerProfile::factory()->create();

    $this->transaction = Transaction::create([
        'farmer_profile_id' => $this->profile->id,
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

    // a line cannot exist without the entry it hangs off
    $this->entry = JournalEntry::create([
        'transaction_id' => $this->transaction->id,
        'posted_at' => now(),
    ]);

    $this->debitSide = [
        'journal_entry_id' => $this->entry->id,
        'ledger_account_id' => $this->cash->id,
        // copied from the transaction so one farmer's books read in a single pass
        'farmer_profile_id' => $this->profile->id,
        'transaction_date' => now()->toDateString(),
        'debit_minor' => 25000,
        'credit_minor' => 0,
        'line_number' => 1,
    ];

    $this->creditSide = [
        'journal_entry_id' => $this->entry->id,
        'ledger_account_id' => $this->sales->id,
        'farmer_profile_id' => $this->profile->id,
        'transaction_date' => now()->toDateString(),
        'debit_minor' => 0,
        'credit_minor' => 25000,
        'line_number' => 2,
    ];
});

it('records a line on the debit side', function () {
    $line = JournalLine::create($this->debitSide);

    expect($line->debit_minor)->toBe(25000);
    expect($line->credit_minor)->toBe(0);
});

it('records a line on the credit side', function () {
    $line = JournalLine::create($this->creditSide);

    expect($line->debit_minor)->toBe(0);
    expect($line->credit_minor)->toBe(25000);
});

// every report starts from one farmer over a range of days
it('carries the farmer and the date it happened', function () {
    $line = JournalLine::create($this->debitSide);

    expect($line->farmer_profile_id)->toBe($this->profile->id);
    expect($line->transaction_date->toDateString())->toBe(now()->toDateString());
});

// a line with no farmer is money nobody can see
it('refuses a line with no farmer', function () {
    expect(fn() => JournalLine::create(['farmer_profile_id' => null] + $this->debitSide))
        ->toThrow(QueryException::class);
});

it('refuses a line with no date', function () {
    expect(fn() => JournalLine::create(['transaction_date' => null] + $this->debitSide))
        ->toThrow(QueryException::class);
});

it('belongs to a farmer', function () {
    $line = JournalLine::create($this->debitSide);

    expect($line->farmerProfile->id)->toBe($this->profile->id);
});

// a line sitting on both sides at once means nobody decided what happened
it('refuses a line with money on both sides', function () {
    expect(fn() => JournalLine::create(['credit_minor' => 5000] + $this->debitSide))
        ->toThrow(InvalidArgumentException::class);
});

it('refuses a line with no money on either side', function () {
    expect(fn() => JournalLine::create(['debit_minor' => 0] + $this->debitSide))
        ->toThrow(InvalidArgumentException::class);
});

// the side a line sits on carries the sign, so the figure itself is never negative
it('refuses a negative debit', function () {
    expect(fn() => JournalLine::create(['debit_minor' => -100] + $this->debitSide))
        ->toThrow(InvalidArgumentException::class);
});

it('refuses a negative credit', function () {
    expect(fn() => JournalLine::create(['credit_minor' => -100] + $this->creditSide))
        ->toThrow(InvalidArgumentException::class);
});

it('needs an account to post against', function () {
    expect(fn() => JournalLine::create(['ledger_account_id' => null] + $this->debitSide))
        ->toThrow(QueryException::class);
});

// a line pointing at an entry that is not there belongs to nobody
it('needs an entry to hang off', function () {
    expect(fn() => JournalLine::create(['journal_entry_id' => 99999] + $this->debitSide))
        ->toThrow(QueryException::class);
});

it('knows which account it posted against', function () {
    $line = JournalLine::create($this->debitSide);

    expect($line->ledgerAccount->name)->toBe('Cash on Hand');
});

// reports read one account across a date range, so the side must be plain to ask about
it('says which side it sits on', function () {
    expect(JournalLine::create($this->debitSide)->isDebit())->toBeTrue();
    expect(JournalLine::create($this->creditSide)->isDebit())->toBeFalse();
});

it('gives the amount whichever side it is on', function () {
    expect(JournalLine::create($this->debitSide)->amountMinor())->toBe(25000);
    expect(JournalLine::create($this->creditSide)->amountMinor())->toBe(25000);
});

// a book you can edit is not a book
it('cannot be changed once written', function () {
    $line = JournalLine::create($this->debitSide);

    expect(fn() => $line->update(['debit_minor' => 999]))
        ->toThrow(RuntimeException::class);
});

it('cannot be deleted', function () {
    $line = JournalLine::create($this->debitSide);

    expect(fn() => $line->delete())->toThrow(RuntimeException::class);
});

// two lines claiming the same position in one entry cannot both be right
it('refuses two lines with the same number in one entry', function () {
    JournalLine::create($this->debitSide);

    expect(fn() => JournalLine::create(['ledger_account_id' => $this->sales->id] + $this->debitSide))
        ->toThrow(QueryException::class);
});
