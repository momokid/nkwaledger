<?php

use App\Models\AccountingPeriod;
use App\Models\Community;
use App\Models\FarmerProfile;
use App\Models\FarmType;
use App\Models\FarmUnit;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\LedgerCategory;
use App\Models\LedgerClass;
use App\Models\LedgerControl;
use App\Models\LedgerSubcategory;
use App\Models\LedgerType;
use App\Models\Transaction;
use App\Models\TransactionTemplate;
use App\Models\User;
use App\Services\Ledger\PostingRequest;
use App\Services\Ledger\PostingService;
use Illuminate\Support\Facades\DB;
use App\Enums\MovementReason;
use App\Models\FarmUnitStock;
use App\Models\FarmUnitStockMovement;

beforeEach(function () {
    $drClass = LedgerClass::create(['name' => 'Dr']);
    $crClass = LedgerClass::create(['name' => 'Cr']);

    $assets = LedgerCategory::create(['name' => 'Assets', 'class_id' => $drClass->id]);
    $income = LedgerCategory::create(['name' => 'Income', 'class_id' => $crClass->id]);
    $expenses = LedgerCategory::create(['name' => 'Expenses', 'class_id' => $drClass->id]);

    $assetSub = LedgerSubcategory::create(['category_id' => $assets->id, 'name' => 'Short Term Asset']);
    $incomeSub = LedgerSubcategory::create(['category_id' => $income->id, 'name' => 'Farm Income']);
    $expenseSub = LedgerSubcategory::create(['category_id' => $expenses->id, 'name' => 'Farm Expense']);

    $control = LedgerControl::create(['name' => 'Cash Ctrl']);
    $type = LedgerType::create(['name' => 'GL']);

    $account = function (string $name, int $subcategoryId) use ($control, $type) {
        return LedgerAccount::create([
            'name' => $name,
            'control_id' => $control->id,
            'subcategory_id' => $subcategoryId,
            'type_id' => $type->id,
        ]);
    };

    $this->cash = $account('Cash on Hand', $assetSub->id);
    $this->momo = $account('Mobile Money', $assetSub->id);
    $this->sales = $account('Crop Sales', $incomeSub->id);
    $this->feed = $account('Feed Expense', $expenseSub->id);
    $this->livestock = $account('Livestock', $assetSub->id);
    $this->lossOnStock = $account('Loss on Livestock', $incomeSub->id);

    // money comes in, so the settlement account replaces the debit leg
    $this->saleTemplate = TransactionTemplate::create([
        'name' => 'I sold crops',
        'slug' => 'crop_sale',
        'transaction_type' => 'INCOME',
        'debit_account_id' => $this->cash->id,
        'credit_account_id' => $this->sales->id,
        'settlement_side' => 'debit',
    ]);

    // money goes out, so the settlement account replaces the credit leg
    $this->feedTemplate = TransactionTemplate::create([
        'name' => 'I bought feed',
        'slug' => 'feed_purchase',
        'transaction_type' => 'EXPENSE',
        'debit_account_id' => $this->feed->id,
        'credit_account_id' => $this->cash->id,
        'settlement_side' => 'credit',
        'requires_farm_unit' => true,
    ]);

    // nothing is paid or received, so no settlement account is used at all
    $this->lossTemplate = TransactionTemplate::create([
        'name' => 'An animal died',
        'slug' => 'livestock_loss',
        'transaction_type' => 'LOSS',
        'debit_account_id' => $this->lossOnStock->id,
        'credit_account_id' => $this->livestock->id,
        'settlement_side' => 'none',
        'requires_farm_unit' => true,
    ]);

    $this->period = AccountingPeriod::create([
        'name' => 'Test Period',
        'starts_on' => now()->startOfYear()->toDateString(),
        'ends_on' => now()->endOfYear()->toDateString(),
    ]);

    $this->profile = FarmerProfile::factory()->create();
    $this->staff = User::factory()->create();

    $this->approvedUnit = FarmUnit::factory()->create([
        'farmer_profile_id' => $this->profile->id,
        'approved_at' => now()->subMonth(),
        'approved_by' => $this->staff->id,
    ]);

    $this->pendingUnit = FarmUnit::factory()->create([
        'farmer_profile_id' => $this->profile->id,
        'approved_at' => null,
    ]);

    $this->service = app(PostingService::class);

    $this->request = function (array $overrides = []) {
        return new PostingRequest(...array_merge([
            'farmerProfileId' => $this->profile->id,
            'transactionTemplateId' => $this->saleTemplate->id,
            'amount' => '250.75',
            'settlementAccountId' => $this->cash->id,
            'transactionDate' => now()->toDateString(),
            'farmUnitId' => null,
            'narration' => null,
            'channel' => 'web',
            'recordedBy' => $this->staff->id,
            'idempotencyKey' => null,
        ], $overrides));
    };
});

it('posts a transaction from what the farmer typed', function () {
    $transaction = $this->service->post(($this->request)());

    expect($transaction)->toBeInstanceOf(Transaction::class);
    expect($transaction->amount_minor)->toBe(25075);
    expect($transaction->transaction_type)->toBe('INCOME');
});

// the type comes from the template, so a farmer can never choose it
it('takes the type from the template, not the caller', function () {
    $transaction = $this->service->post(($this->request)([
        'transactionTemplateId' => $this->feedTemplate->id,
        'farmUnitId' => $this->approvedUnit->id,
    ]));

    expect($transaction->transaction_type)->toBe('EXPENSE');
});

it('writes one entry with two balanced lines', function () {
    $transaction = $this->service->post(($this->request)());

    $entry = JournalEntry::where('transaction_id', $transaction->id)->first();

    expect($entry->lines)->toHaveCount(2);
    expect($entry->isBalanced())->toBeTrue();
    expect($entry->totalDebitMinor())->toBe(25075);
});

// money coming in lands wherever the farmer says it landed
it('puts the settlement account on the debit side for income', function () {
    $transaction = $this->service->post(($this->request)([
        'settlementAccountId' => $this->momo->id,
    ]));

    $entry = JournalEntry::where('transaction_id', $transaction->id)->first();

    expect($entry->lines[0]->ledger_account_id)->toBe($this->momo->id);
    expect($entry->lines[0]->debit_minor)->toBe(25075);
    expect($entry->lines[1]->ledger_account_id)->toBe($this->sales->id);
    expect($entry->lines[1]->credit_minor)->toBe(25075);
});

// money going out comes from wherever the farmer says it came from
it('puts the settlement account on the credit side for an expense', function () {
    $transaction = $this->service->post(($this->request)([
        'transactionTemplateId' => $this->feedTemplate->id,
        'settlementAccountId' => $this->momo->id,
        'farmUnitId' => $this->approvedUnit->id,
    ]));

    $entry = JournalEntry::where('transaction_id', $transaction->id)->first();

    expect($entry->lines[0]->ledger_account_id)->toBe($this->feed->id);
    expect($entry->lines[0]->debit_minor)->toBe(25075);
    expect($entry->lines[1]->ledger_account_id)->toBe($this->momo->id);
    expect($entry->lines[1]->credit_minor)->toBe(25075);
});

// an animal dying moves no money, so both legs come straight from the template
it('ignores the settlement account when the template names no side', function () {
    FarmUnitStock::factory()->create(['farm_unit_id' => $this->approvedUnit->id]);

    $transaction = $this->service->post(($this->request)([
        'transactionTemplateId' => $this->lossTemplate->id,
        'settlementAccountId' => $this->cash->id,
        'farmUnitId' => $this->approvedUnit->id,
        'quantityLost' => '1',
    ]));

    $entry = JournalEntry::where('transaction_id', $transaction->id)->first();

    expect($transaction->settlement_account_id)->toBeNull();
    expect($entry->lines[0]->ledger_account_id)->toBe($this->lossOnStock->id);
    expect($entry->lines[1]->ledger_account_id)->toBe($this->livestock->id);
});

it('finds the period from the date it happened', function () {
    $transaction = $this->service->post(($this->request)());

    expect($transaction->accounting_period_id)->toBe($this->period->id);
});

it('refuses a date no period covers', function () {
    expect(fn() => $this->service->post(($this->request)([
        'transactionDate' => now()->addYears(5)->toDateString(),
    ])))->toThrow(App\Exceptions\Ledger\PostingFailed::class);
});

it('refuses a date inside a closed period', function () {
    $this->period->close($this->staff);

    expect(fn() => $this->service->post(($this->request)()))
        ->toThrow(App\Exceptions\Ledger\PostingFailed::class);
});

// a farmer cannot know today about something that happens next week
it('refuses a date in the future', function () {
    expect(fn() => $this->service->post(($this->request)([
        'transactionDate' => now()->addDay()->toDateString(),
    ])))->toThrow(App\Exceptions\Ledger\PostingFailed::class);
});

it('accepts a back dated entry inside the open period', function () {
    $transaction = $this->service->post(($this->request)([
        'transactionDate' => now()->subDays(10)->toDateString(),
    ]));

    expect($transaction->transaction_date->toDateString())
        ->toBe(now()->subDays(10)->toDateString());
});

// the sticker goes on at the moment of writing, from the unit as it stands right now
it('marks nothing provisional when the unit is approved', function () {
    $transaction = $this->service->post(($this->request)([
        'transactionTemplateId' => $this->feedTemplate->id,
        'farmUnitId' => $this->approvedUnit->id,
    ]));

    expect($transaction->is_provisional)->toBeFalse();
});

it('marks it provisional when the unit is not approved', function () {
    $transaction = $this->service->post(($this->request)([
        'transactionTemplateId' => $this->feedTemplate->id,
        'farmUnitId' => $this->pendingUnit->id,
    ]));

    expect($transaction->is_provisional)->toBeTrue();
});

// approving the pen in June cannot bless what was written in May
it('leaves the sticker alone when the unit is approved later', function () {
    $transaction = $this->service->post(($this->request)([
        'transactionTemplateId' => $this->feedTemplate->id,
        'farmUnitId' => $this->pendingUnit->id,
    ]));

    $this->pendingUnit->forceFill([
        'approved_at' => now(),
        'approved_by' => $this->staff->id,
    ])->save();

    expect($transaction->fresh()->is_provisional)->toBeTrue();
});

it('marks nothing provisional when no unit is needed', function () {
    $transaction = $this->service->post(($this->request)());

    expect($transaction->is_provisional)->toBeFalse();
});

it('refuses a template that needs a unit when none is given', function () {
    expect(fn() => $this->service->post(($this->request)([
        'transactionTemplateId' => $this->feedTemplate->id,
        'farmUnitId' => null,
    ])))->toThrow(App\Exceptions\Ledger\PostingFailed::class);
});

// one farmer's pen cannot appear in another farmer's books
it('refuses a unit belonging to somebody else', function () {
    $stranger = FarmUnit::factory()->create(['approved_at' => now()]);

    expect(fn() => $this->service->post(($this->request)([
        'transactionTemplateId' => $this->feedTemplate->id,
        'farmUnitId' => $stranger->id,
    ])))->toThrow(App\Exceptions\Ledger\PostingFailed::class);
});

it('refuses an amount of zero', function () {
    expect(fn() => $this->service->post(($this->request)(['amount' => '0'])))
        ->toThrow(App\Exceptions\Ledger\PostingFailed::class);
});

it('refuses an amount that is not a number', function () {
    expect(fn() => $this->service->post(($this->request)(['amount' => 'two fifty'])))
        ->toThrow(App\Exceptions\Ledger\PostingFailed::class);
});

it('refuses a template that is switched off', function () {
    $this->saleTemplate->update(['is_active' => false]);

    expect(fn() => $this->service->post(($this->request)()))
        ->toThrow(App\Exceptions\Ledger\PostingFailed::class);
});

// the phone is not making a mistake, it is retrying after a bad network
it('returns the same transaction when a key arrives twice', function () {
    $first = $this->service->post(($this->request)(['idempotencyKey' => 'abc-123']));
    $second = $this->service->post(($this->request)(['idempotencyKey' => 'abc-123']));

    expect($second->id)->toBe($first->id);
    expect(Transaction::count())->toBe(1);
});

it('treats different keys as different postings', function () {
    $this->service->post(($this->request)(['idempotencyKey' => 'abc-123']));
    $this->service->post(($this->request)(['idempotencyKey' => 'def-456']));

    expect(Transaction::count())->toBe(2);
});

// half a posting is worse than none, so a failure leaves nothing behind
it('writes nothing at all when the posting fails', function () {
    try {
        $this->service->post(($this->request)([
            'transactionDate' => now()->addYears(5)->toDateString(),
        ]));
    } catch (App\Exceptions\Ledger\PostingFailed) {
        // the throw is the point, the counts below are the test
    }

    expect(Transaction::count())->toBe(0);
    expect(JournalEntry::count())->toBe(0);
    expect(DB::table('journal_lines')->count())->toBe(0);
});

it('keeps the narration the farmer wrote', function () {
    $transaction = $this->service->post(($this->request)([
        'narration' => 'Sold maize at Kejetia',
    ]));

    expect($transaction->narration)->toBe('Sold maize at Kejetia');

    $entry = JournalEntry::where('transaction_id', $transaction->id)->first();

    expect($entry->narration)->toBe('Sold maize at Kejetia');
});

it('records which door the posting came through', function () {
    $transaction = $this->service->post(($this->request)(['channel' => 'ussd']));

    expect($transaction->channel)->toBe('ussd');
});

it('gives every posting a reference a farmer can read out', function () {
    $transaction = $this->service->post(($this->request)());

    expect($transaction->reference)->toMatch('/^\d{12}$/');
});

it('records the quantity lost and reduces the unit\'s stock', function () {
    $stock = FarmUnitStock::factory()->create([
        'farm_unit_id' => $this->approvedUnit->id,
        'opening_quantity' => 50,
    ]);

    $transaction = $this->service->post(($this->request)([
        'transactionTemplateId' => $this->lossTemplate->id,
        'settlementAccountId' => null,
        'farmUnitId' => $this->approvedUnit->id,
        'quantityLost' => '6',
    ]));

    expect($transaction->quantity_lost)->toBe('6.00');
    expect($stock->fresh()->current_quantity)->toBe('44.00');
});

it('creates a movement for the quantity lost, unconfirmed', function () {
    $stock = FarmUnitStock::factory()->create([
        'farm_unit_id' => $this->approvedUnit->id,
        'opening_quantity' => 50,
    ]);

    $this->service->post(($this->request)([
        'transactionTemplateId' => $this->lossTemplate->id,
        'settlementAccountId' => null,
        'farmUnitId' => $this->approvedUnit->id,
        'quantityLost' => '6',
    ]));

    $movement = $stock->fresh()->movements()->where('reason', MovementReason::Loss)->first();

    expect($movement)->not->toBeNull();
    expect($movement->quantity)->toBe('6.00');
    expect($movement->isConfirmed())->toBeFalse();
});

it('refuses a loss with no quantity for a unit that has one', function () {
    FarmUnitStock::factory()->create(['farm_unit_id' => $this->approvedUnit->id]);

    expect(fn() => $this->service->post(($this->request)([
        'transactionTemplateId' => $this->lossTemplate->id,
        'settlementAccountId' => null,
        'farmUnitId' => $this->approvedUnit->id,
    ])))->toThrow(App\Exceptions\Ledger\PostingFailed::class);
});

it('refuses a loss when the unit has no single active stock to reduce', function () {
    FarmUnitStock::factory()->create(['farm_unit_id' => $this->approvedUnit->id, 'ended_on' => now()]);

    expect(fn() => $this->service->post(($this->request)([
        'transactionTemplateId' => $this->lossTemplate->id,
        'settlementAccountId' => null,
        'farmUnitId' => $this->approvedUnit->id,
        'quantityLost' => '6',
    ])))->toThrow(App\Exceptions\Ledger\PostingFailed::class);
});

// nobody knows which animal died, so the loss is shared by size, not by age
it('splits a loss proportionally across active batches by their share of the count', function () {
    $big = FarmUnitStock::factory()->create([
        'farm_unit_id' => $this->approvedUnit->id,
        'opening_quantity' => 30,
        'started_on' => now()->subMonth(),
    ]);

    $small = FarmUnitStock::factory()->create([
        'farm_unit_id' => $this->approvedUnit->id,
        'opening_quantity' => 20,
        'started_on' => now()->subMonths(6),
    ]);

    $this->service->post(($this->request)([
        'transactionTemplateId' => $this->lossTemplate->id,
        'settlementAccountId' => null,
        'farmUnitId' => $this->approvedUnit->id,
        'quantityLost' => '5',
    ]));

    // 30/50 of the herd and 20/50 of the herd, so 3 and 2 of the loss
    expect($big->fresh()->current_quantity)->toBe('27.00');
    expect($small->fresh()->current_quantity)->toBe('18.00');

    $bigMovement = $big->fresh()->movements()->where('reason', MovementReason::Loss)->first();
    $smallMovement = $small->fresh()->movements()->where('reason', MovementReason::Loss)->first();

    expect($bigMovement->quantity)->toBe('3.00');
    expect($smallMovement->quantity)->toBe('2.00');
});

// three even batches cannot split a loss of 1 into clean thirds, so the leftover
// pesewa-equivalent lands on whichever batch still has room, and the total still adds up
it('gives any rounding remainder to a batch with room, so the split still totals exactly', function () {
    $stocks = FarmUnitStock::factory()->count(3)->create([
        'farm_unit_id' => $this->approvedUnit->id,
        'opening_quantity' => 10,
    ]);

    $this->service->post(($this->request)([
        'transactionTemplateId' => $this->lossTemplate->id,
        'settlementAccountId' => null,
        'farmUnitId' => $this->approvedUnit->id,
        'quantityLost' => '1',
    ]));

    $quantities = FarmUnitStockMovement::query()
        ->where('reason', MovementReason::Loss)
        ->pluck('quantity')
        ->map(fn($quantity) => (float) $quantity)
        ->sort()
        ->values();

    expect($quantities->sum())->toBe(1.0);
    expect($quantities->toArray())->toBe([0.33, 0.33, 0.34]);
});

// the farm cannot lose more animals than it has on record, even split across batches
it('refuses a loss bigger than everything the farm has across all active batches', function () {
    FarmUnitStock::factory()->create([
        'farm_unit_id' => $this->approvedUnit->id,
        'opening_quantity' => 10,
        'started_on' => now()->subMonths(3),
    ]);

    FarmUnitStock::factory()->create([
        'farm_unit_id' => $this->approvedUnit->id,
        'opening_quantity' => 5,
        'started_on' => now()->subMonth(),
    ]);

    expect(fn() => $this->service->post(($this->request)([
        'transactionTemplateId' => $this->lossTemplate->id,
        'settlementAccountId' => null,
        'farmUnitId' => $this->approvedUnit->id,
        'quantityLost' => '20',
    ])))->toThrow(App\Exceptions\Ledger\PostingFailed::class);
});
