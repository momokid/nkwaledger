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
use App\Models\Notification;
use App\Models\SyncSubmission;
use App\Models\Transaction;
use App\Models\TransactionTemplate;
use App\Models\User;
use App\Models\UserPermissionDenial;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

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
    $this->sales = LedgerAccount::create(['name' => 'Sales', 'control_id' => $control->id, 'subcategory_id' => $incomeSub->id, 'type_id' => $type->id]);
    $this->template = TransactionTemplate::create([
        'name' => 'I sold crops', 'slug' => 'crop_sale', 'transaction_type' => 'INCOME',
        'debit_account_id' => $this->cash->id, 'credit_account_id' => $this->sales->id, 'settlement_side' => 'debit',
    ]);
    AccountingPeriod::create(['name' => 'Test', 'starts_on' => now()->startOfYear()->toDateString(), 'ends_on' => now()->endOfYear()->toDateString()]);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->farmerUser = User::factory()->create();
    $this->farmerUser->assignRole('farmer');
    $this->profile = FarmerProfile::factory()->create(['user_id' => $this->farmerUser->id]);
});

function nfRow(array $payload = [], string $status = 'needs_fixing', string $reason = 'It was sent back.', ?User $by = null, string $type = 'transaction'): SyncSubmission
{
    return SyncSubmission::create([
        'client_uuid' => (string) Str::uuid(),
        'type' => $type,
        'user_id' => ($by ?? test()->farmerUser)->id,
        'farmer_profile_id' => test()->profile->id,
        'payload' => $payload + ['template' => test()->template->id, 'amount' => '100', 'settlement_account_id' => test()->cash->id, 'event_date' => now()->toDateString()],
        'device_date' => now()->toDateString(),
        'received_at' => now(),
        'status' => $status,
        'reason' => $reason,
    ]);
}

function rejectedRow(): SyncSubmission
{
    return nfRow([], 'rejected', 'Not a sale.');
}

function nfAdmin(string ...$denied): User
{
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    foreach ($denied as $name) {
        UserPermissionDenial::create(['user_id' => $admin->id, 'denied_by' => test()->admin->id, 'permission_id' => Permission::where('name', $name)->value('id')]);
    }

    return $admin;
}

function nfAjax(User $user)
{
    return test()->actingAs($user)->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json']);
}

function nfFlagged(?User $user = null): array
{
    return test()->actingAs($user ?? test()->farmerUser)->get('/my-records')->assertOk()->viewData('page')['props']['flagged'];
}

describe('the admin approves a record that needs a fix', function () {
    it('posts it once, and it leaves the farmer\'s list', function () {
        $row = nfRow();

        $result = nfAjax($this->admin)->postJson("/admin/sync-submissions/{$row->uuid}/approve")->assertOk()->json();

        expect($result['status'])->toBe('accepted')
            ->and($result['reference'])->not->toBeNull()
            ->and(Transaction::count())->toBe(1)
            ->and(nfFlagged())->toBe([]);
    });

    it('is safe to repeat: the second time posts nothing', function () {
        $row = nfRow();

        nfAjax($this->admin)->postJson("/admin/sync-submissions/{$row->uuid}/approve")->assertOk();
        nfAjax($this->admin)->postJson("/admin/sync-submissions/{$row->uuid}/approve")->assertStatus(422)->assertJsonPath('message', 'This record has already been decided.');

        expect(Transaction::count())->toBe(1);
    });

    it('is refused when the record still breaks the ledger rules, and stays needing a fix', function () {
        // Sales is not a place money can be taken in or paid out
        $row = nfRow(['settlement_account_id' => $this->sales->id]);
        $before = Notification::count();

        $result = nfAjax($this->admin)->postJson("/admin/sync-submissions/{$row->uuid}/approve")->assertOk()->json();

        expect($result['status'])->toBe('needs_fixing')
            ->and($result['reason'])->not->toBeEmpty()
            ->and(Transaction::count())->toBe(0)
            ->and($row->fresh()->status)->toBe('needs_fixing')
            ->and(collect(nfFlagged())->pluck('status')->all())->toBe(['needs_fixing'])
            ->and(Notification::count())->toBe($before);
    });

    it('is refused when the amount cannot be read, and stays needing a fix', function () {
        $row = nfRow(['amount' => 'lots']);

        $result = nfAjax($this->admin)->postJson("/admin/sync-submissions/{$row->uuid}/approve")->assertOk()->json();

        expect($result['status'])->toBe('needs_fixing')->and(Transaction::count())->toBe(0);
    });

    it('can be tried again after a refusal', function () {
        $row = nfRow(['settlement_account_id' => $this->sales->id]);
        nfAjax($this->admin)->postJson("/admin/sync-submissions/{$row->uuid}/approve")->assertOk();

        $row->forceFill(['payload' => array_merge($row->payload, ['settlement_account_id' => $this->cash->id])])->save();

        expect(nfAjax($this->admin)->postJson("/admin/sync-submissions/{$row->uuid}/approve")->assertOk()->json('status'))->toBe('accepted');
    });

    it('writes an audit entry', function () {
        $row = nfRow();

        nfAjax($this->admin)->postJson("/admin/sync-submissions/{$row->uuid}/approve")->assertOk();

        expect(AuditLog::where('action', 'sync.submission_approved')->where('auditable_id', $row->id)->count())->toBe(1);
    });

    it('needs the approve permission', function () {
        $row = nfRow();

        nfAjax(nfAdmin('sync-submissions.approve'))->postJson("/admin/sync-submissions/{$row->uuid}/approve")->assertForbidden();
        nfAjax($this->farmerUser)->postJson("/admin/sync-submissions/{$row->uuid}/approve")->assertForbidden();

        expect(Transaction::count())->toBe(0)->and($row->fresh()->status)->toBe('needs_fixing');
    });

    it('does not post a health report, which has no ledger entry to make', function () {
        $row = nfRow(['description' => 'Weak birds', 'template' => null], 'needs_fixing', 'A record could not be saved.', null, 'health_report');

        nfAjax($this->admin)->postJson("/admin/sync-submissions/{$row->uuid}/approve")->assertStatus(422);

        expect(Transaction::count())->toBe(0)->and($row->fresh()->status)->toBe('needs_fixing');
    });
});

describe('the admin rejects a record that needs a fix', function () {
    it('moves it to rejected, and the farmer sees it under rejected with the reason', function () {
        $row = nfRow();

        $result = nfAjax($this->admin)->postJson("/admin/sync-submissions/{$row->uuid}/reject", ['reason' => '<b>Not a sale.</b>'])->assertOk()->json();

        expect($result['status'])->toBe('rejected')
            ->and($result['reason'])->toBe('<b>Not a sale.</b>');

        $flagged = nfFlagged();
        expect($flagged)->toHaveCount(1)
            ->and($flagged[0]['status'])->toBe('rejected')
            ->and($flagged[0]['reason'])->toBe('<b>Not a sale.</b>')
            ->and($flagged[0]['uuid'])->toBe($row->uuid);
    });

    it('writes an audit entry, and a second reject is safe', function () {
        $row = nfRow();

        nfAjax($this->admin)->postJson("/admin/sync-submissions/{$row->uuid}/reject", ['reason' => 'No.'])->assertOk();
        nfAjax($this->admin)->postJson("/admin/sync-submissions/{$row->uuid}/reject", ['reason' => 'Again.'])->assertStatus(422);

        expect(AuditLog::where('action', 'sync.submission_rejected')->where('auditable_id', $row->id)->count())->toBe(1)
            ->and($row->fresh()->reason)->toBe('No.');
    });

    it('needs the reject permission', function () {
        $row = nfRow();

        nfAjax(nfAdmin('sync-submissions.reject'))->postJson("/admin/sync-submissions/{$row->uuid}/reject", ['reason' => 'No.'])->assertForbidden();

        expect($row->fresh()->status)->toBe('needs_fixing');
    });
});

describe('the admin screen', function () {
    it('lists records that need a fix beside the held ones, and not health reports', function () {
        $fix = nfRow();
        $held = nfRow([], 'held_for_review', 'An admin will look at it.');
        nfRow(['description' => 'Weak birds'], 'needs_fixing', 'A record could not be saved.', null, 'health_report');
        nfRow([], 'accepted');

        $this->actingAs($this->admin)->get('/admin/sync-submissions')->assertOk()->assertInertia(
            fn($page) => $page->has('submissions.data', 2)->where('submissions.data.0.reason', fn($reason) => in_array($reason, [$fix->reason, $held->reason], true)),
        );
    });
});

describe('the farmer dismisses a rejected record', function () {
    it('hides it from that farmer, and keeps it on the server', function () {
        $row = rejectedRow();
        expect(collect(nfFlagged())->pluck('status')->all())->toBe(['rejected']);

        test()->actingAs($this->farmerUser)->postJson("/my-records/rejected/{$row->uuid}/dismiss")->assertOk();

        expect(nfFlagged())->toBe([])
            ->and(SyncSubmission::whereKey($row->id)->exists())->toBeTrue()
            ->and($row->fresh()->status)->toBe('rejected')
            ->and($row->fresh()->dismissed_at)->not->toBeNull();
    });

    it('is safe twice', function () {
        $row = rejectedRow();

        test()->actingAs($this->farmerUser)->postJson("/my-records/rejected/{$row->uuid}/dismiss")->assertOk();
        $first = $row->fresh()->dismissed_at;
        test()->actingAs($this->farmerUser)->postJson("/my-records/rejected/{$row->uuid}/dismiss")->assertOk();

        expect($row->fresh()->dismissed_at->equalTo($first))->toBeTrue();
    });

    it('answers 404 to anyone but the record\'s own farmer', function () {
        $row = rejectedRow();
        $other = User::factory()->create();
        $other->assignRole('farmer');
        FarmerProfile::factory()->create(['user_id' => $other->id]);
        $agent = User::factory()->create();
        $agent->assignRole('agent');

        test()->actingAs($other)->postJson("/my-records/rejected/{$row->uuid}/dismiss")->assertNotFound();
        test()->actingAs($agent)->postJson("/my-records/rejected/{$row->uuid}/dismiss")->assertNotFound();
        test()->actingAs($this->admin)->postJson("/my-records/rejected/{$row->uuid}/dismiss")->assertNotFound();

        expect($row->fresh()->dismissed_at)->toBeNull();
    });

    it('answers 404 for a record that is not rejected, or not a uuid', function () {
        $fix = nfRow();
        $held = nfRow([], 'held_for_review');

        test()->actingAs($this->farmerUser)->postJson("/my-records/rejected/{$fix->uuid}/dismiss")->assertNotFound();
        test()->actingAs($this->farmerUser)->postJson("/my-records/rejected/{$held->uuid}/dismiss")->assertNotFound();
        test()->actingAs($this->farmerUser)->postJson('/my-records/rejected/not-a-uuid/dismiss')->assertNotFound();

        expect($fix->fresh()->dismissed_at)->toBeNull();
    });

    it('never comes back, even when other records change', function () {
        $row = rejectedRow();
        test()->actingAs($this->farmerUser)->postJson("/my-records/rejected/{$row->uuid}/dismiss")->assertOk();
        nfRow();

        expect(collect(nfFlagged())->pluck('uuid')->all())->not->toContain($row->uuid);
    });

    it('sends no notice and writes nothing to the audit log', function () {
        $row = rejectedRow();
        $notices = Notification::count();
        $audits = AuditLog::count();

        test()->actingAs($this->farmerUser)->postJson("/my-records/rejected/{$row->uuid}/dismiss")->assertOk();

        expect(Notification::count())->toBe($notices)->and(AuditLog::count())->toBe($audits);
    });

    it('does not hide it from another farmer who has their own rejected record', function () {
        $mine = rejectedRow();
        test()->actingAs($this->farmerUser)->postJson("/my-records/rejected/{$mine->uuid}/dismiss")->assertOk();

        $other = User::factory()->create();
        $other->assignRole('farmer');
        $otherProfile = FarmerProfile::factory()->create(['user_id' => $other->id]);
        SyncSubmission::create([
            'client_uuid' => (string) Str::uuid(), 'type' => 'transaction', 'user_id' => $other->id, 'farmer_profile_id' => $otherProfile->id,
            'payload' => ['template' => $this->template->id, 'amount' => '5'], 'device_date' => now()->toDateString(), 'received_at' => now(), 'status' => 'rejected', 'reason' => 'No.',
        ]);

        expect(nfFlagged($other))->toHaveCount(1);
    });
});

describe('what the farmer\'s page is given', function () {
    it('still holds back the reason of a held record, and shows no reason for a held one', function () {
        nfRow([], 'held_for_review', 'An internal note.');

        $row = nfFlagged()[0];

        expect($row['status'])->toBe('held')->and($row['reason'])->toBeNull();
    });

    it('gives only public uuids', function () {
        rejectedRow();

        expect(nfFlagged()[0])->not->toHaveKeys(['id', 'user_id', 'farmer_profile_id', 'transaction_id', 'dismissed_at']);
    });
});

describe('what a review leaves on the record', function () {
    it('a failed approve sets no reviewer and no reviewed time, keeps the status, and refreshes the reason', function () {
        $row = nfRow(['settlement_account_id' => $this->sales->id], 'needs_fixing', 'An old reason.');

        nfAjax($this->admin)->postJson("/admin/sync-submissions/{$row->uuid}/approve")->assertOk();

        $fresh = $row->fresh();
        expect($fresh->reviewed_by)->toBeNull()
            ->and($fresh->reviewed_at)->toBeNull()
            ->and($fresh->status)->toBe('needs_fixing')
            ->and($fresh->reason)->not->toBe('An old reason.')
            ->and($fresh->reason)->not->toBeEmpty();
    });

    it('a failed approve writes an audit entry with the admin, the record and the reason', function () {
        $row = nfRow(['settlement_account_id' => $this->sales->id]);

        nfAjax($this->admin)->postJson("/admin/sync-submissions/{$row->uuid}/approve")->assertOk();

        $entry = AuditLog::where('action', 'sync.submission_approve_failed')->firstOrFail();
        expect($entry->user_id)->toBe($this->admin->id)
            ->and($entry->auditable_id)->toBe($row->id)
            ->and($entry->new_values['reason'])->toBe($row->fresh()->reason)
            ->and(AuditLog::where('action', 'sync.submission_approved')->count())->toBe(0);
    });

    it('a blocked approve changes nothing on the record and still writes an audit entry', function () {
        $row = nfRow(['description' => 'Weak birds', 'template' => null], 'needs_fixing', 'A record could not be saved.', null, 'health_report');

        nfAjax($this->admin)->postJson("/admin/sync-submissions/{$row->uuid}/approve")->assertStatus(422);

        $fresh = $row->fresh();
        expect($fresh->reviewed_by)->toBeNull()->and($fresh->reviewed_at)->toBeNull()->and($fresh->reason)->toBe('A record could not be saved.');

        $entry = AuditLog::where('action', 'sync.submission_approve_blocked')->firstOrFail();
        expect($entry->user_id)->toBe($this->admin->id)->and($entry->auditable_id)->toBe($row->id)->and($entry->new_values['reason'])->toBe('A record could not be saved.');
    });

    it('a real approve sets the reviewer and the reviewed time', function () {
        $row = nfRow();

        nfAjax($this->admin)->postJson("/admin/sync-submissions/{$row->uuid}/approve")->assertOk();

        expect($row->fresh()->reviewed_by)->toBe($this->admin->id)->and($row->fresh()->reviewed_at)->not->toBeNull();
    });

    it('a real reject sets the reviewer and the reviewed time', function () {
        $row = nfRow();

        nfAjax($this->admin)->postJson("/admin/sync-submissions/{$row->uuid}/reject", ['reason' => 'No.'])->assertOk();

        expect($row->fresh()->reviewed_by)->toBe($this->admin->id)->and($row->fresh()->reviewed_at)->not->toBeNull();
    });

    it('holds a held record to the same rules', function () {
        $failing = nfRow(['settlement_account_id' => $this->sales->id], 'held_for_review', 'An admin will look at it.');
        $good = nfRow([], 'held_for_review', 'An admin will look at it.');

        nfAjax($this->admin)->postJson("/admin/sync-submissions/{$failing->uuid}/approve")->assertOk();
        nfAjax($this->admin)->postJson("/admin/sync-submissions/{$good->uuid}/approve")->assertOk();

        expect($failing->fresh()->reviewed_by)->toBeNull()
            ->and($failing->fresh()->reviewed_at)->toBeNull()
            ->and(AuditLog::where('action', 'sync.submission_approve_failed')->where('auditable_id', $failing->id)->count())->toBe(1)
            ->and($good->fresh()->reviewed_by)->toBe($this->admin->id)
            ->and($good->fresh()->status)->toBe('accepted');
    });
});
