<?php

use App\Models\AccountingPeriod;
use App\Models\FarmerProfile;
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
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Schema;
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

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $farmer = function (array $user = []) {
        $account = User::factory()->create($user);
        $account->assignRole('farmer');

        return [$account, FarmerProfile::factory()->create(['user_id' => $account->id])];
    };

    [$this->userA, $this->profileA] = $farmer();
    [$this->userB, $this->profileB] = $farmer();
    $this->farmer = $farmer;
});

function uuidRecord(FarmerProfile $profile, string $uuid): array
{
    return [
        'uuid' => $uuid,
        'template' => test()->template->id,
        'farmer' => $profile->uuid,
        'amount' => '100',
        'settlement_account_id' => test()->cash->id,
        'event_date' => now()->toDateString(),
        'device_created_at' => now()->toIso8601String(),
    ];
}

function uuidSync(User $user, FarmerProfile $profile, string $uuid): array
{
    return test()->actingAs($user)->postJson('/sync/submissions', ['records' => [uuidRecord($profile, $uuid)]])->assertOk()->json('results.0');
}

function rowFor(User $user, string $uuid): SyncSubmission
{
    return SyncSubmission::where('user_id', $user->id)->where('client_uuid', $uuid)->firstOrFail();
}

test('two users submitting the same uuid are both accepted and see only their own data', function () {
    $uuid = (string) Str::uuid();

    $a = uuidSync($this->userA, $this->profileA, $uuid);
    $b = uuidSync($this->userB, $this->profileB, $uuid);

    expect($a['status'])->toBe('accepted')->and($b['status'])->toBe('accepted')
        ->and($a['reference'])->not->toBe($b['reference'])
        ->and(SyncSubmission::count())->toBe(2)
        ->and(Transaction::count())->toBe(2)
        ->and(Transaction::where('idempotency_key', "sync.{$this->userA->id}.{$uuid}")->count())->toBe(1)
        ->and(Transaction::where('idempotency_key', "sync.{$this->userB->id}.{$uuid}")->count())->toBe(1);

    expect($this->actingAs($this->userA)->getJson('/sync/submissions')->json('data'))->toHaveCount(1)
        ->and($this->actingAs($this->userB)->getJson('/sync/submissions')->json('data.0.reference'))->toBe($b['reference']);
});

test('the same user sending the same uuid twice gets the stored result and posts once', function () {
    $uuid = (string) Str::uuid();

    $first = uuidSync($this->userA, $this->profileA, $uuid);
    $second = uuidSync($this->userA, $this->profileA, $uuid);

    expect($second)->toBe($first)->and(SyncSubmission::count())->toBe(1)->and(Transaction::count())->toBe(1);
});

test('admin approve and reject act only on the row id given when two held rows share a uuid', function () {
    $uuid = (string) Str::uuid();
    $this->userA->update(['is_active' => false]);
    $this->userB->update(['is_active' => false]);

    uuidSync($this->userA, $this->profileA, $uuid);
    uuidSync($this->userB, $this->profileB, $uuid);

    $a = rowFor($this->userA, $uuid);
    $b = rowFor($this->userB, $uuid);

    $this->actingAs($this->admin)->postJson("/admin/sync-submissions/{$a->uuid}/approve")->assertOk();

    expect($a->fresh()->status)->toBe('accepted')->and($b->fresh()->status)->toBe('held_for_review')->and(Transaction::count())->toBe(1);

    $this->actingAs($this->admin)->postJson("/admin/sync-submissions/{$b->uuid}/reject", ['reason' => 'No.'])->assertOk();

    expect($b->fresh()->status)->toBe('rejected')->and($a->fresh()->status)->toBe('accepted');
});

test('approve and reject on an id that does not exist are a 404, and non-admins get 403', function () {
    $missing = (string) Str::uuid();

    $this->actingAs($this->admin)->postJson("/admin/sync-submissions/{$missing}/approve")->assertNotFound();
    $this->actingAs($this->admin)->postJson("/admin/sync-submissions/{$missing}/reject", ['reason' => 'x'])->assertNotFound();

    $this->actingAs($this->userA)->postJson("/admin/sync-submissions/{$missing}/approve")->assertForbidden();
    $this->actingAs($this->userA)->postJson("/admin/sync-submissions/{$missing}/reject", ['reason' => 'x'])->assertForbidden();
});

test('the migration swaps the global unique index for a per user one, and back', function () {
    $migration = require database_path('migrations/2026_10_04_183139_make_sync_submission_uuid_unique_per_user.php');
    $uniques = fn() => collect(Schema::getIndexes('sync_submissions'))->where('unique', true)->pluck('columns')->map(fn($c) => implode(',', $c))->all();

    expect($uniques())->toContain('user_id,client_uuid')->not->toContain('client_uuid');

    $migration->down();
    expect($uniques())->toContain('client_uuid')->not->toContain('user_id,client_uuid');

    $migration->up();
    expect($uniques())->toContain('user_id,client_uuid')->not->toContain('client_uuid');
});
