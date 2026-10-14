<?php

use App\Models\AccountingPeriod;
use App\Models\FarmerProfile;
use App\Models\LedgerAccount;
use App\Models\LedgerCategory;
use App\Models\LedgerClass;
use App\Models\LedgerControl;
use App\Models\LedgerSubcategory;
use App\Models\LedgerType;
use App\Models\Notification;
use App\Models\SyncSubmission;
use App\Models\Transaction;
use App\Models\TransactionTemplate;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Arr;
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

    $this->agent = User::factory()->create();
    $this->agent->assignRole('agent');
    $this->otherAgent = User::factory()->create();
    $this->otherAgent->assignRole('agent');

    $this->mine = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agent->id]);
    $this->theirs = FarmerProfile::factory()->create(['assigned_agent_id' => $this->otherAgent->id]);
});

function unknownRecord(string $farmer, ?string $uuid = null): array
{
    return [
        'uuid' => $uuid ?? (string) Str::uuid(), 'template' => test()->template->id, 'farmer' => $farmer, 'amount' => '100',
        'settlement_account_id' => test()->cash->id, 'event_date' => now()->toDateString(), 'device_created_at' => now()->toIso8601String(),
    ];
}

function unknownSend(User $user, array ...$records)
{
    return test()->actingAs($user)->postJson('/sync/submissions', ['records' => $records]);
}

test('a farmer uuid that does not exist is stored held, with no 422 on the batch', function () {
    $ghost = (string) Str::uuid();

    $result = unknownSend($this->agent, unknownRecord($ghost))->assertOk()->json('results.0');
    $row = SyncSubmission::first();

    expect($result['status'])->toBe('held_for_review')
        ->and($row->farmer_profile_id)->toBeNull()
        ->and($row->payload['farmer'])->toBe($ghost)
        ->and(Transaction::count())->toBe(0);
});

test('an unknown farmer and an out-of-scope farmer give the same result apart from the uuid', function () {
    $unknown = unknownSend($this->agent, unknownRecord((string) Str::uuid()))->assertOk()->json('results.0');
    $outOfScope = unknownSend($this->agent, unknownRecord($this->theirs->uuid))->assertOk()->json('results.0');

    expect(Arr::except($unknown, 'uuid'))->toBe(Arr::except($outOfScope, 'uuid'))
        ->and($unknown['status'])->toBe('held_for_review');
});

test('a malformed farmer value still gets the structural 422', function () {
    unknownSend($this->agent, unknownRecord('not-a-uuid'))->assertStatus(422);
});

test('other records in the same batch are processed normally', function () {
    $results = unknownSend($this->agent, unknownRecord($this->mine->uuid), unknownRecord((string) Str::uuid()), unknownRecord($this->mine->uuid))->assertOk()->json('results');

    expect(array_column($results, 'status'))->toBe(['accepted', 'held_for_review', 'accepted'])->and(Transaction::count())->toBe(2);
});

test('only the submitter is told, with the existing hold text', function () {
    unknownSend($this->agent, unknownRecord((string) Str::uuid()))->assertOk();

    expect(Notification::count())->toBe(1)
        ->and(Notification::first()->user_id)->toBe($this->agent->id)
        ->and(Notification::first()->message)->toBe('A record is waiting for review.');
});

test('the review page lists such a record with Unknown as the farmer', function () {
    unknownSend($this->agent, unknownRecord((string) Str::uuid()))->assertOk();

    $response = $this->actingAs($this->admin)->get('/admin/sync-submissions')->assertOk();

    expect($response->viewData('page')['props']['submissions']['data'][0]['farmer'])->toBe('Unknown');
});

test('approving such a record is refused, posts nothing and is not a 500', function () {
    unknownSend($this->agent, unknownRecord((string) Str::uuid()))->assertOk();
    $uuid = SyncSubmission::first()->uuid;

    $this->actingAs($this->admin)->postJson("/admin/sync-submissions/{$uuid}/approve")->assertStatus(422);

    expect(Transaction::count())->toBe(0)->and(SyncSubmission::first()->status)->toBe('held_for_review');

    // and an admin can still turn it down
    $this->actingAs($this->admin)->postJson("/admin/sync-submissions/{$uuid}/reject", ['reason' => 'No.'])->assertOk();

    expect(SyncSubmission::first()->status)->toBe('rejected');
});

test('listing by a nonexistent farmer is the same 404 as an out-of-scope one', function () {
    $this->actingAs($this->agent)->getJson('/sync/submissions?farmer=' . Str::uuid())->assertNotFound();
    $this->actingAs($this->agent)->getJson("/sync/submissions?farmer={$this->theirs->uuid}")->assertNotFound();
});

test('the migration makes the farmer reference nullable, and back', function () {
    $migration = require database_path('migrations/2026_10_05_101413_make_sync_submission_farmer_nullable.php');
    $nullable = fn() => collect(Schema::getColumns('sync_submissions'))->firstWhere('name', 'farmer_profile_id')['nullable'];

    expect($nullable())->toBeTrue();

    $migration->down();
    expect($nullable())->toBeFalse();

    $migration->up();
    expect($nullable())->toBeTrue();
});
