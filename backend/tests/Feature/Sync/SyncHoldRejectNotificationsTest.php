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
use App\Models\TransactionTemplate;
use App\Models\User;
use App\Services\SyncSubmissionService;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

const HOLD_TEXT = 'A record is waiting for review.';

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
});

function noteFarmer(array $user = [], bool $withAgent = true): array
{
    $account = User::factory()->create($user);
    $account->assignRole('farmer');

    return [$account, FarmerProfile::factory()->create(['user_id' => $account->id, 'assigned_agent_id' => $withAgent ? test()->agent->id : null])];
}

function noteRecord(FarmerProfile $profile, ?string $uuid = null): array
{
    return [
        'uuid' => $uuid ?? (string) Str::uuid(), 'template' => test()->template->id, 'farmer' => $profile->uuid, 'amount' => '100',
        'settlement_account_id' => test()->cash->id, 'event_date' => now()->toDateString(), 'device_created_at' => now()->toIso8601String(),
    ];
}

function noteSend(User $user, array $record)
{
    return test()->actingAs($user)->postJson('/sync/submissions', ['records' => [$record]])->assertOk();
}

function notesFor(User $user, string $kind): array
{
    return Notification::where('user_id', $user->id)->where('kind', $kind)->pluck('message')->all();
}

test('a record from an inactive user is held and the submitter is told', function () {
    [$farmer, $profile] = noteFarmer(['is_active' => false], withAgent: false);

    noteSend($farmer, noteRecord($profile));

    expect(notesFor($farmer, 'sync.held'))->toBe([HOLD_TEXT])->and(Notification::count())->toBe(1);
});

test('a record from a user not allowed to act for that farmer is held and the submitter is told', function () {
    [, $profile] = noteFarmer();

    noteSend($this->otherAgent, noteRecord($profile));

    expect(notesFor($this->otherAgent, 'sync.held'))->toBe([HOLD_TEXT])->and(Notification::count())->toBe(1);
});

test('a farmer\'s held record also tells the assigned agent, and an agent\'s own held record tells them once', function () {
    [$farmer, $profile] = noteFarmer(['is_active' => false]);

    noteSend($farmer, noteRecord($profile));

    expect(notesFor($farmer, 'sync.held'))->toBe([HOLD_TEXT])->and(notesFor($this->agent, 'sync.held'))->toBe([HOLD_TEXT]);

    Notification::query()->delete();
    $this->agent->update(['is_active' => false]);

    noteSend($this->agent, noteRecord($profile));

    expect(notesFor($this->agent, 'sync.held'))->toBe([HOLD_TEXT])->and(Notification::count())->toBe(1);
});

test('a retry of a held record sends no second notification, including through the race replay', function () {
    [$farmer, $profile] = noteFarmer(['is_active' => false], withAgent: false);
    $record = noteRecord($profile);

    noteSend($farmer, $record);
    noteSend($farmer, $record);

    expect(Notification::count())->toBe(1);

    // a twin finishes between the lookup and the insert (the race, simulated)
    $raced = noteRecord($profile);
    $active = true;
    $done = false;

    DB::listen(function ($query) use (&$active, &$done, $raced, $farmer) {
        if ($active && ! $done && str_contains($query->sql, 'from "sync_submissions" where "client_uuid"') && in_array($raced['uuid'], $query->bindings, true)) {
            $done = true;
            app(SyncSubmissionService::class)->submit($farmer, $raced);
        }
    });

    try {
        noteSend($farmer, $raced);
    } finally {
        $active = false;
    }

    expect(Notification::count())->toBe(2);
});

test('an admin reject tells the submitter and the assigned agent with the reason', function () {
    [$farmer, $profile] = noteFarmer(['is_active' => false]);
    noteSend($farmer, noteRecord($profile));
    $uuid = SyncSubmission::first()->uuid;

    $this->actingAs($this->admin)->postJson("/admin/sync-submissions/{$uuid}/reject", ['reason' => 'Not a real sale.'])->assertOk();

    expect(notesFor($farmer, 'sync.rejected'))->toBe(['A record was not accepted. Not a real sale.'])
        ->and(notesFor($this->agent, 'sync.rejected'))->toBe(['A record was not accepted. Not a real sale.'])
        ->and(Notification::where('kind', 'sync.rejected')->whereNotNull('link')->count())->toBe(0);
});

test('a second reject is the existing 422 and sends nothing', function () {
    [$farmer, $profile] = noteFarmer(['is_active' => false], withAgent: false);
    noteSend($farmer, noteRecord($profile));
    $uuid = SyncSubmission::first()->uuid;

    $this->actingAs($this->admin)->postJson("/admin/sync-submissions/{$uuid}/reject", ['reason' => 'No.'])->assertOk();
    $before = Notification::count();

    $this->actingAs($this->admin)->postJson("/admin/sync-submissions/{$uuid}/reject", ['reason' => 'No.'])->assertStatus(422);

    expect(Notification::count())->toBe($before);
});

test('approving sends nothing new', function () {
    [$farmer, $profile] = noteFarmer(['is_active' => false], withAgent: false);
    noteSend($farmer, noteRecord($profile));
    $uuid = SyncSubmission::first()->uuid;
    $before = Notification::count();

    $this->actingAs($this->admin)->postJson("/admin/sync-submissions/{$uuid}/approve")->assertOk();

    expect(SyncSubmission::first()->status)->toBe('accepted')->and(Notification::count())->toBe($before);
});

test('the texts are exact and hold no number', function () {
    [$farmer, $profile] = noteFarmer(['is_active' => false], withAgent: false);
    noteSend($farmer, noteRecord($profile));
    $uuid = SyncSubmission::first()->uuid;
    $this->actingAs($this->admin)->postJson("/admin/sync-submissions/{$uuid}/reject", ['reason' => 'Not a real sale.'])->assertOk();

    expect(Notification::pluck('message')->all())->toBe([HOLD_TEXT, 'A record was not accepted. Not a real sale.']);

    foreach (Notification::all() as $note) {
        expect($note->message)->not->toMatch('/\d/')->and($note->link)->toBeNull();
    }
});
