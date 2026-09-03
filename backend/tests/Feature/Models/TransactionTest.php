<?php

use App\Models\AccountingPeriod;
use App\Models\FarmerProfile;
use App\Models\LedgerAccount;
use App\Models\LedgerCategory;
use App\Models\LedgerClass;
use App\Models\LedgerControl;
use App\Models\LedgerSubcategory;
use App\Models\LedgerType;
use App\Models\Transaction;
use App\Models\TransactionTemplate;
use App\Models\User;
use Illuminate\Support\Str;

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

    $this->template = TransactionTemplate::create([
        'name' => 'I sold crops',
        'slug' => 'crop_sale',
        'transaction_type' => 'INCOME',
        'debit_account_id' => $this->cash->id,
        'credit_account_id' => $this->sales->id,
        'settlement_side' => 'debit',
    ]);

    $this->period = AccountingPeriod::create([
        'name' => 'Test Period',
        'starts_on' => now()->startOfYear()->toDateString(),
        'ends_on' => now()->endOfYear()->toDateString(),
    ]);

    $this->profile = FarmerProfile::factory()->create();
    $this->staff = User::factory()->create();

    $this->payload = [
        'farmer_profile_id' => $this->profile->id,
        'transaction_template_id' => $this->template->id,
        'transaction_type' => 'INCOME',
        'accounting_period_id' => $this->period->id,
        'transaction_date' => now()->toDateString(),
        'amount_minor' => 25000,
        'settlement_account_id' => $this->cash->id,
        'channel' => 'web',
        'is_provisional' => false,
        'recorded_by' => $this->staff->id,
        'posted_at' => now(),
    ];
});

it('records a transaction with its required fields', function () {
    $transaction = Transaction::create($this->payload);

    expect($transaction->transaction_type)->toBe('INCOME');
    expect($transaction->amount_minor)->toBe(25000);
    expect($transaction->channel)->toBe('web');
});

// the row id never leaves the server, so every url needs something else
it('gives every transaction a uuid', function () {
    $transaction = Transaction::create($this->payload);

    expect($transaction->uuid)->not->toBeNull();
    expect(Str::isUuid($transaction->uuid))->toBeTrue();
});

it('uses the uuid in urls', function () {
    $transaction = Transaction::create($this->payload);

    expect($transaction->getRouteKey())->toBe($transaction->uuid);
});

// this is the number a farmer reads out on a phone call
it('gives every transaction a twelve digit reference', function () {
    $transaction = Transaction::create($this->payload);

    expect($transaction->reference)->toMatch('/^\d{12}$/');
});

it('keeps a reference that starts with a zero', function () {
    $transaction = Transaction::create($this->payload + ['reference' => '000000000123']);

    expect($transaction->fresh()->reference)->toBe('000000000123');
});

it('never gives two transactions the same reference', function () {
    $first = Transaction::create($this->payload);
    $second = Transaction::create($this->payload);

    expect($second->reference)->not->toBe($first->reference);
});

// nothing about the reference should tell an outsider when or how many
it('does not encode the date in the reference', function () {
    $transaction = Transaction::create($this->payload);

    expect($transaction->reference)->not->toContain(now()->format('Y'));
});

// the override goes on the left, because array plus keeps the left side
it('rejects an amount of zero', function () {
    expect(fn() => Transaction::create(['amount_minor' => 0] + $this->payload))
        ->toThrow(InvalidArgumentException::class);
});

// the plus or minus lives in the journal lines, never here
it('rejects a negative amount', function () {
    expect(fn() => Transaction::create(['amount_minor' => -100] + $this->payload))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects a type it does not know', function () {
    expect(fn() => Transaction::create(['transaction_type' => 'REFUND'] + $this->payload))
        ->toThrow(InvalidArgumentException::class);
});

// a correction of a correction hides the trail
it('rejects an adjustment that reverses another adjustment', function () {
    $original = Transaction::create(['transaction_type' => 'ADJUSTMENT'] + $this->payload);

    expect(fn() => Transaction::create([
        'transaction_type' => 'ADJUSTMENT',
        'reverses_transaction_id' => $original->id,
    ] + $this->payload))->toThrow(InvalidArgumentException::class);
});

it('only lets an adjustment reverse something', function () {
    $original = Transaction::create($this->payload);

    expect(fn() => Transaction::create([
        'transaction_type' => 'INCOME',
        'reverses_transaction_id' => $original->id,
    ] + $this->payload))->toThrow(InvalidArgumentException::class);
});

it('reverses the same transaction only once', function () {
    $original = Transaction::create($this->payload);

    Transaction::create([
        'transaction_type' => 'ADJUSTMENT',
        'reverses_transaction_id' => $original->id,
    ] + $this->payload);

    expect(fn() => Transaction::create([
        'transaction_type' => 'ADJUSTMENT',
        'reverses_transaction_id' => $original->id,
    ] + $this->payload))->toThrow(Illuminate\Database\QueryException::class);
});

// a book you can edit is not a book
it('cannot be changed once written', function () {
    $transaction = Transaction::create($this->payload);

    expect(fn() => $transaction->update(['amount_minor' => 999]))
        ->toThrow(RuntimeException::class);
});

it('cannot be deleted', function () {
    $transaction = Transaction::create($this->payload);

    expect(fn() => $transaction->delete())->toThrow(RuntimeException::class);
});

it('belongs to a farmer, a template and a period', function () {
    $transaction = Transaction::create($this->payload);

    expect($transaction->farmerProfile->id)->toBe($this->profile->id);
    expect($transaction->template->slug)->toBe('crop_sale');
    expect($transaction->accountingPeriod->id)->toBe($this->period->id);
});

it('knows who recorded it and where it came from', function () {
    $transaction = Transaction::create($this->payload);

    expect($transaction->recordedBy->id)->toBe($this->staff->id);
    expect($transaction->settlementAccount->name)->toBe('Cash on Hand');
});
