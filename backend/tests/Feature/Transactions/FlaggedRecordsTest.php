<?php

use App\Models\FarmerProfile;
use App\Models\LedgerAccount;
use App\Models\LedgerCategory;
use App\Models\LedgerClass;
use App\Models\LedgerControl;
use App\Models\LedgerSubcategory;
use App\Models\LedgerType;
use App\Models\SyncSubmission;
use App\Models\TransactionTemplate;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
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
    $cash = LedgerAccount::create(['name' => 'Cash', 'control_id' => $control->id, 'subcategory_id' => $assetSub->id, 'type_id' => $type->id, 'is_settlement' => true]);
    $sales = LedgerAccount::create(['name' => 'Sales', 'control_id' => $control->id, 'subcategory_id' => $incomeSub->id, 'type_id' => $type->id]);
    $this->template = TransactionTemplate::create([
        'name' => 'I sold crops', 'slug' => 'crop_sale', 'transaction_type' => 'INCOME',
        'debit_account_id' => $cash->id, 'credit_account_id' => $sales->id, 'settlement_side' => 'debit',
    ]);

    $this->agent = User::factory()->create();
    $this->agent->assignRole('agent');
    $this->farmerUser = User::factory()->create();
    $this->farmerUser->assignRole('farmer');
    $this->profile = FarmerProfile::factory()->create(['user_id' => $this->farmerUser->id, 'assigned_agent_id' => $this->agent->id]);
});

function flagged(User $user, FarmerProfile $profile, string $status, array $payload = [], ?string $reason = null, string $type = 'transaction'): SyncSubmission
{
    return SyncSubmission::create([
        'client_uuid' => (string) Str::uuid(),
        'type' => $type,
        'user_id' => $user->id,
        'farmer_profile_id' => $profile->id,
        'payload' => $payload + ['template' => test()->template->id, 'amount' => '120.50'],
        'device_date' => '2026-03-01',
        'received_at' => now(),
        'status' => $status,
        'reason' => $reason,
    ]);
}

function flaggedRows(User $user): array
{
    return test()->actingAs($user)->get('/my-records')->assertOk()->viewData('page')['props']['flagged'];
}

it('lists the farmer\'s own needing-a-fix and waiting-for-a-check records', function () {
    flagged($this->farmerUser, $this->profile, 'needs_fixing', [], 'The amount is too high.');
    flagged($this->farmerUser, $this->profile, 'held_for_review', [], 'An internal note an admin wrote.');

    $rows = collect(flaggedRows($this->farmerUser));

    expect($rows)->toHaveCount(2);

    $fix = $rows->firstWhere('status', 'needs_fixing');
    expect($fix['reason'])->toBe('The amount is too high.')
        ->and($fix['kind'])->toBe('record')
        ->and($fix['template'])->toBe('I sold crops')
        ->and($fix['amount'])->toBe('120.50')
        ->and($fix['event_date'])->toBe('2026-03-01');

    $held = $rows->firstWhere('status', 'held');
    expect($held['reason'])->toBeNull();
});

it('hands over the reason exactly as the server holds it, for the page to show as plain text', function () {
    flagged($this->farmerUser, $this->profile, 'needs_fixing', [], '<b>x</b> & <script>alert(1)</script>');

    expect(flaggedRows($this->farmerUser)[0]['reason'])->toBe('<b>x</b> & <script>alert(1)</script>');
});

it('lists a health report that needs a fix, read-only, with its description', function () {
    flagged($this->farmerUser, $this->profile, 'needs_fixing', ['description' => 'Weak birds', 'template' => null, 'amount' => null], 'A record could not be saved.', 'health_report');

    $row = flaggedRows($this->farmerUser)[0];

    expect($row['kind'])->toBe('health_report')
        ->and($row['description'])->toBe('Weak birds')
        ->and($row['reason'])->toBe('A record could not be saved.');
});

it('never lists another person\'s records, or any that are settled', function () {
    $other = User::factory()->create();
    $other->assignRole('farmer');
    $otherProfile = FarmerProfile::factory()->create(['user_id' => $other->id]);

    flagged($other, $otherProfile, 'needs_fixing');
    flagged($this->agent, $this->profile, 'needs_fixing');
    flagged($this->farmerUser, $this->profile, 'accepted');
    flagged($this->farmerUser, $this->profile, 'superseded');

    expect(flaggedRows($this->farmerUser))->toBe([]);
});

it('gives no numeric id to the page', function () {
    flagged($this->farmerUser, $this->profile, 'needs_fixing');

    $row = flaggedRows($this->farmerUser)[0];

    expect($row)->not->toHaveKeys(['id', 'user_id', 'farmer_profile_id', 'transaction_id'])
        ->and(Str::isUuid($row['uuid']))->toBeTrue();
});
