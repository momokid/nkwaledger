<?php

use App\Models\AccountingPeriod;
use App\Models\AuditLog;
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

    $this->farmerUser = User::factory()->create(['is_active' => false]);
    $this->farmerUser->assignRole('farmer');
    $this->profile = FarmerProfile::factory()->create(['user_id' => $this->farmerUser->id]);

    // a suspended user's record lands held
    $this->uuid = (string) Str::uuid();
    $this->actingAs($this->farmerUser)->postJson('/sync/submissions', ['records' => [[
        'uuid' => $this->uuid, 'template' => $this->template->id, 'farmer' => $this->profile->uuid, 'amount' => '100',
        'settlement_account_id' => $this->cash->id, 'event_date' => now()->toDateString(), 'device_created_at' => now()->toIso8601String(),
    ]]])->assertOk();
});

test('an admin approving posts it through the ledger exactly once, even when called twice', function () {
    $this->actingAs($this->admin)->postJson("/admin/sync-submissions/{$this->uuid}/approve")->assertOk();
    $this->actingAs($this->admin)->postJson("/admin/sync-submissions/{$this->uuid}/approve")->assertStatus(422);

    $row = SyncSubmission::first();

    expect(Transaction::count())->toBe(1)
        ->and($row->status)->toBe('accepted')
        ->and($row->transaction_id)->toBe(Transaction::first()->id)
        ->and($row->reviewed_by)->toBe($this->admin->id)
        ->and($row->reviewed_at)->not->toBeNull()
        ->and(Transaction::first()->recorded_by)->toBe($this->farmerUser->id)
        ->and(AuditLog::where('action', 'sync.submission_approved')->count())->toBe(1);
});

test('approving still runs the posting checks', function () {
    $this->template->update(['is_active' => false]);

    $this->actingAs($this->admin)->postJson("/admin/sync-submissions/{$this->uuid}/approve")->assertOk();

    expect(SyncSubmission::first()->status)->toBe('needs_fixing')->and(Transaction::count())->toBe(0);
});

test('an admin can reject with a reason', function () {
    $this->actingAs($this->admin)->postJson("/admin/sync-submissions/{$this->uuid}/reject", ['reason' => 'Not a real sale.'])->assertOk();

    $row = SyncSubmission::first();

    expect($row->status)->toBe('rejected')
        ->and($row->reason)->toBe('Not a real sale.')
        ->and($row->reviewed_by)->toBe($this->admin->id)
        ->and(Transaction::count())->toBe(0)
        ->and(AuditLog::where('action', 'sync.submission_rejected')->count())->toBe(1);
});

test('rejecting needs a reason and a held submission', function () {
    $this->actingAs($this->admin)->postJson("/admin/sync-submissions/{$this->uuid}/reject", ['reason' => ''])->assertStatus(422);

    $this->actingAs($this->admin)->postJson("/admin/sync-submissions/{$this->uuid}/reject", ['reason' => 'No.'])->assertOk();
    $this->actingAs($this->admin)->postJson("/admin/sync-submissions/{$this->uuid}/reject", ['reason' => 'No.'])->assertStatus(422);
});

test('a hold is written to the audit log', function () {
    expect(AuditLog::where('action', 'sync.submission_held')->count())->toBe(1);
});

test('non-admins get 403 on the review routes', function () {
    $agent = User::factory()->create();
    $agent->assignRole('agent');

    foreach ([$agent, $this->farmerUser] as $user) {
        $this->actingAs($user)->postJson("/admin/sync-submissions/{$this->uuid}/approve")->assertForbidden();
        $this->actingAs($user)->postJson("/admin/sync-submissions/{$this->uuid}/reject", ['reason' => 'x'])->assertForbidden();
    }

    expect(Transaction::count())->toBe(0);
});
