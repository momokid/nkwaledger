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
use App\Models\ReversalRequest;
use App\Models\TransactionTemplate;
use App\Models\User;
use App\Services\Ledger\PostingRequest;
use App\Services\Ledger\PostingService;
use App\Services\Ledger\ReversalService;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);

    $drClass = LedgerClass::create(['name' => 'Dr']);
    $crClass = LedgerClass::create(['name' => 'Cr']);

    $assets = LedgerCategory::create(['name' => 'Assets', 'class_id' => $drClass->id]);
    $income = LedgerCategory::create(['name' => 'Income', 'class_id' => $crClass->id]);

    $assetSub = LedgerSubcategory::create(['category_id' => $assets->id, 'name' => 'Money']);
    $incomeSub = LedgerSubcategory::create(['category_id' => $income->id, 'name' => 'Farm Income']);

    $control = LedgerControl::create(['name' => 'General']);
    $type = LedgerType::create(['name' => 'GL']);

    $this->cash = LedgerAccount::create([
        'name' => 'Cash A/C',
        'control_id' => $control->id,
        'subcategory_id' => $assetSub->id,
        'type_id' => $type->id,
        'is_settlement' => true,
    ]);

    $this->sales = LedgerAccount::create([
        'name' => 'Income on Sales',
        'control_id' => $control->id,
        'subcategory_id' => $incomeSub->id,
        'type_id' => $type->id,
    ]);

    $this->saleTemplate = TransactionTemplate::create([
        'name' => 'I sold my farm produce',
        'slug' => 'produce_sale',
        'transaction_type' => 'INCOME',
        'debit_account_id' => $this->cash->id,
        'credit_account_id' => $this->sales->id,
        'settlement_side' => 'debit',
    ]);

    TransactionTemplate::create([
        'name' => 'Correction of an earlier record',
        'slug' => 'correction',
        'transaction_type' => 'ADJUSTMENT',
        'debit_account_id' => $this->cash->id,
        'credit_account_id' => $this->sales->id,
        'settlement_side' => 'none',
    ]);

    AccountingPeriod::create([
        'name' => 'Test Period',
        'starts_on' => now()->startOfYear()->toDateString(),
        'ends_on' => now()->endOfYear()->toDateString(),
    ]);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->otherAdmin = User::factory()->create();
    $this->otherAdmin->assignRole('admin');

    $this->agent = User::factory()->create();
    $this->agent->assignRole('agent');

    $this->farmerUser = User::factory()->create();
    $this->farmerUser->assignRole('farmer');

    $this->profile = FarmerProfile::factory()->create([
        'user_id' => $this->farmerUser->id,
        'assigned_agent_id' => $this->agent->id,
    ]);

    $this->record = app(PostingService::class)->post(new PostingRequest(
        farmerProfileId: $this->profile->id,
        transactionTemplateId: $this->saleTemplate->id,
        amount: '250',
        settlementAccountId: $this->cash->id,
        transactionDate: now()->subDay()->toDateString(),
        narration: 'Sold maize',
        recordedBy: $this->farmerUser->id,
    ));

    $this->ask = fn() => app(ReversalService::class)
        ->request($this->record, $this->farmerUser, 'I typed the wrong amount.');
});

it('records a notification', function () {
    $note = Notification::create([
        'user_id' => $this->admin->id,
        'kind' => 'reversal.requested',
        'message' => 'Somebody wants to cancel a record.',
        'link' => '/admin/approvals',
    ]);

    expect($note->message)->toBe('Somebody wants to cancel a record.');
    expect($note->read_at)->toBeNull();
});

// a cancellation waiting on somebody is news for whoever can agree
it('tells everyone who can agree that a cancellation was asked for', function () {
    ($this->ask)();

    expect(Notification::where('user_id', $this->admin->id)->where('kind', 'reversal.requested')->exists())
        ->toBeTrue();
    expect(Notification::where('user_id', $this->otherAdmin->id)->where('kind', 'reversal.requested')->exists())
        ->toBeTrue();
});

it('does not tell somebody who cannot agree', function () {
    ($this->ask)();

    expect(Notification::where('user_id', $this->agent->id)->exists())->toBeFalse();
    expect(Notification::where('user_id', $this->farmerUser->id)->exists())->toBeFalse();
});

// nobody needs telling about their own ask
it('does not tell the person who asked', function () {
    app(ReversalService::class)->request($this->record, $this->admin, 'My own mistake.');

    expect(Notification::where('user_id', $this->admin->id)->where('kind', 'reversal.requested')->exists())
        ->toBeFalse();
});

it('tells the asker when the cancellation is agreed', function () {
    $request = ($this->ask)();

    app(ReversalService::class)->approve($request, $this->admin);

    expect(Notification::where('user_id', $this->farmerUser->id)->where('kind', 'reversal.approved')->exists())
        ->toBeTrue();
});

it('tells the asker when the cancellation is refused', function () {
    $request = ($this->ask)();

    app(ReversalService::class)->reject($request, $this->admin, 'The original was right.');

    expect(Notification::where('user_id', $this->farmerUser->id)->where('kind', 'reversal.rejected')->exists())
        ->toBeTrue();
});

it('says why it was refused', function () {
    $request = ($this->ask)();

    app(ReversalService::class)->reject($request, $this->admin, 'The original was right.');

    expect(Notification::where('kind', 'reversal.rejected')->first()->message)
        ->toContain('The original was right.');
});

it('carries a link to the thing it is about', function () {
    ($this->ask)();

    expect(Notification::where('kind', 'reversal.requested')->first()->link)->not->toBeEmpty();
});

// the bell shows the count, the dropdown shows the list
it('sends the unread count to every page', function () {
    ($this->ask)();

    $this->actingAs($this->admin)
        ->get('/admin/approvals')
        ->assertInertia(fn($page) => $page->where('auth.unreadNotifications', 1));
});

it('counts nothing for somebody with no notifications', function () {
    $this->actingAs($this->admin)
        ->get('/admin/approvals')
        ->assertInertia(fn($page) => $page->where('auth.unreadNotifications', 0));
});

it('lists a person their own notifications', function () {
    ($this->ask)();

    $this->actingAs($this->admin)
        ->getJson('/notifications')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('never lists somebody else their notifications', function () {
    ($this->ask)();

    $this->actingAs($this->agent)
        ->getJson('/notifications')
        ->assertJsonCount(0, 'data');
});

// newest first, so the thing that just happened is at the top
it('lists the newest first', function () {
    $older = Notification::create([
        'user_id' => $this->admin->id,
        'kind' => 'test',
        'message' => 'Older one.',
    ]);

    $older->forceFill(['created_at' => now()->subDays(3)])->saveQuietly();

    Notification::create([
        'user_id' => $this->admin->id,
        'kind' => 'test',
        'message' => 'Newer one.',
    ]);

    $this->actingAs($this->admin)
        ->getJson('/notifications')
        ->assertJsonPath('data.0.message', 'Newer one.');
});

it('says which ones have not been read', function () {
    ($this->ask)();

    $this->actingAs($this->admin)
        ->getJson('/notifications')
        ->assertJsonPath('data.0.is_read', false);
});

it('marks one as read', function () {
    ($this->ask)();

    $note = Notification::where('user_id', $this->admin->id)->first();

    $this->actingAs($this->admin)
        ->patchJson("/notifications/{$note->uuid}/read")
        ->assertOk();

    expect($note->fresh()->read_at)->not->toBeNull();
});

// a red number nobody can clear is a number people learn to ignore
it('marks everything as read at once', function () {
    ($this->ask)();

    Notification::create([
        'user_id' => $this->admin->id,
        'kind' => 'test',
        'message' => 'Another one.',
    ]);

    $this->actingAs($this->admin)
        ->patchJson('/notifications/read-all')
        ->assertOk();

    expect(Notification::where('user_id', $this->admin->id)->whereNull('read_at')->count())->toBe(0);
});

it('never marks somebody else notification as read', function () {
    ($this->ask)();

    $note = Notification::where('user_id', $this->admin->id)->first();

    $this->actingAs($this->agent)
        ->patchJson("/notifications/{$note->uuid}/read")
        ->assertNotFound();

    expect($note->fresh()->read_at)->toBeNull();
});

it('stops counting one that has been read', function () {
    ($this->ask)();

    $note = Notification::where('user_id', $this->admin->id)->first();

    $this->actingAs($this->admin)->patchJson("/notifications/{$note->uuid}/read");

    $this->actingAs($this->admin)
        ->get('/admin/approvals')
        ->assertInertia(fn($page) => $page->where('auth.unreadNotifications', 0));
});

// the trail matters, so nothing is thrown away when it is done
it('keeps a notification after the thing it points at is settled', function () {
    $request = ($this->ask)();

    app(ReversalService::class)->approve($request, $this->admin);

    expect(Notification::where('kind', 'reversal.requested')->exists())->toBeTrue();
});

it('turns a guest away', function () {
    $this->get('/notifications')->assertRedirect('/login');
});
