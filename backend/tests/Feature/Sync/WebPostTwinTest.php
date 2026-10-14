<?php

use App\Models\AccountingPeriod;
use App\Models\FarmerProfile;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\LedgerCategory;
use App\Models\LedgerClass;
use App\Models\LedgerControl;
use App\Models\LedgerSubcategory;
use App\Models\LedgerType;
use App\Models\SyncSubmission;
use App\Models\Transaction;
use App\Models\TransactionTemplate;
use App\Models\User;
use App\Services\Ledger\PostingRequest;
use App\Services\Ledger\PostingService;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);

    $dr = LedgerClass::create(['name' => 'Dr']);
    $cr = LedgerClass::create(['name' => 'Cr']);
    $assetSub = LedgerSubcategory::create(['category_id' => LedgerCategory::create(['name' => 'Assets', 'class_id' => $dr->id])->id, 'name' => 'Money']);
    $incomeSub = LedgerSubcategory::create(['category_id' => LedgerCategory::create(['name' => 'Income', 'class_id' => $cr->id])->id, 'name' => 'Farm Income']);
    $control = LedgerControl::create(['name' => 'General']);
    $type = LedgerType::create(['name' => 'GL']);

    $this->cash = LedgerAccount::create(['name' => 'Cash', 'control_id' => $control->id, 'subcategory_id' => $assetSub->id, 'type_id' => $type->id, 'is_settlement' => true]);
    $sales = LedgerAccount::create(['name' => 'Sales', 'control_id' => $control->id, 'subcategory_id' => $incomeSub->id, 'type_id' => $type->id]);
    $this->template = TransactionTemplate::create([
        'name' => 'I sold crops', 'slug' => 'crop_sale', 'transaction_type' => 'INCOME',
        'debit_account_id' => $this->cash->id, 'credit_account_id' => $sales->id, 'settlement_side' => 'debit',
    ]);
    AccountingPeriod::create(['name' => 'Test', 'starts_on' => now()->startOfYear()->toDateString(), 'ends_on' => now()->endOfYear()->toDateString()]);

    $this->userA = User::factory()->create();
    $this->userA->assignRole('farmer');
    $this->profileA = FarmerProfile::factory()->create(['user_id' => $this->userA->id]);
    $this->userB = User::factory()->create();
    $this->userB->assignRole('farmer');
    $this->profileB = FarmerProfile::factory()->create(['user_id' => $this->userB->id]);
});

function twinRecord(FarmerProfile $profile, array $o = []): array
{
    return array_merge([
        'uuid' => (string) Str::uuid(),
        'template' => test()->template->id,
        'farmer' => $profile->uuid,
        'amount' => '100',
        'settlement_account_id' => test()->cash->id,
        'event_date' => now()->toDateString(),
        'device_created_at' => now()->toIso8601String(),
    ], $o);
}

// what the web form does: the raw uuid is the idempotency key
function twinWebPost(User $user, FarmerProfile $profile, string $uuid, string $amount = '100'): Transaction
{
    return app(PostingService::class)->post(new PostingRequest(
        farmerProfileId: $profile->id,
        transactionTemplateId: test()->template->id,
        amount: $amount,
        settlementAccountId: test()->cash->id,
        transactionDate: now()->toDateString(),
        recordedBy: $user->id,
        idempotencyKey: $uuid,
    ));
}

function twinSync(User $user, array $record): array
{
    return test()->actingAs($user)->postJson('/sync/submissions', ['records' => [$record]])->assertOk()->json('results.0');
}

test('a web post followed by the same uuid via sync is accepted and nothing is posted twice', function () {
    $record = twinRecord($this->profileA);
    $web = twinWebPost($this->userA, $this->profileA, $record['uuid']);
    $entries = JournalEntry::count();

    $result = twinSync($this->userA, $record);

    expect($result['status'])->toBe('accepted')
        ->and($result['transaction_uuid'])->toBe($web->uuid)
        ->and(Transaction::count())->toBe(1)
        ->and(JournalEntry::count())->toBe($entries)
        ->and(SyncSubmission::first())->status->toBe('accepted')->transaction_id->toBe($web->id);
});

test('the same uuid with a different amount is refused and nothing is posted', function () {
    $record = twinRecord($this->profileA, ['amount' => '250']);
    twinWebPost($this->userA, $this->profileA, $record['uuid'], '100');

    $result = twinSync($this->userA, $record);

    expect($result)->toBe(['uuid' => $record['uuid'], 'status' => 'error', 'error' => Transaction::KEY_REUSED])
        ->and(Transaction::count())->toBe(1)
        ->and(SyncSubmission::count())->toBe(0);
});

test('a transaction with that uuid made by another user is ignored and left untouched', function () {
    $record = twinRecord($this->profileA);
    $theirs = twinWebPost($this->userB, $this->profileB, $record['uuid']);

    $result = twinSync($this->userA, $record);

    expect($result['status'])->toBe('accepted')
        ->and($result['transaction_uuid'])->not->toBe($theirs->uuid)
        ->and(Transaction::count())->toBe(2)
        ->and(Transaction::where('idempotency_key', "sync.{$this->userA->id}.{$record['uuid']}")->count())->toBe(1)
        ->and($theirs->fresh()->idempotency_key)->toBe($record['uuid'])
        ->and($theirs->fresh()->farmer_profile_id)->toBe($this->profileB->id);
});

test('replaying the same sync record afterwards returns the same result and still one entry', function () {
    $record = twinRecord($this->profileA);
    twinWebPost($this->userA, $this->profileA, $record['uuid']);

    $first = twinSync($this->userA, $record);
    $second = twinSync($this->userA, $record);

    expect($second)->toBe($first)
        ->and(Transaction::count())->toBe(1)
        ->and(SyncSubmission::count())->toBe(1);
});

test('a web post landing while the sync record is being posted leaves one entry', function () {
    $record = twinRecord($this->profileA);
    $web = null;
    $raced = false;

    DB::listen(function ($query) use (&$raced, &$web, $record) {
        if (! $raced && str_contains($query->sql, 'from "transactions" where "idempotency_key"') && in_array($record['uuid'], $query->bindings, true)) {
            $raced = true;
            $web = twinWebPost($this->userA, $this->profileA, $record['uuid']);
        }
    });

    $result = twinSync($this->userA, $record);

    expect($raced)->toBeTrue()
        ->and($result['status'])->toBe('accepted')
        ->and($result['transaction_uuid'])->toBe($web->uuid)
        ->and(Transaction::count())->toBe(1)
        ->and(JournalEntry::count())->toBe(1)
        ->and(SyncSubmission::count())->toBe(1);
});

test('a record with a new uuid posts as before', function () {
    twinWebPost($this->userA, $this->profileA, (string) Str::uuid());
    $record = twinRecord($this->profileA);

    $result = twinSync($this->userA, $record);

    expect($result['status'])->toBe('accepted')
        ->and(Transaction::count())->toBe(2)
        ->and(Transaction::where('idempotency_key', "sync.{$this->userA->id}.{$record['uuid']}")->count())->toBe(1);
});
