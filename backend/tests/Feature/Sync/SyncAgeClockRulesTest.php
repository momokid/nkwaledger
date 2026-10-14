<?php

use App\Models\AccountingPeriod;
use App\Models\AuditLog;
use App\Models\FarmerProfile;
use App\Models\FarmType;
use App\Models\FarmTypeCategory;
use App\Models\FarmUnit;
use App\Models\JournalEntry;
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
use Carbon\Carbon;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Str;

const AR_NOW = '2026-03-10 12:00:00';
const AR_TOO_OLD = 'This record is too old to be accepted.';
const AR_CLOCK_AHEAD = 'The date on the phone was ahead of the real date.';

beforeEach(function () {
    Carbon::setTestNow(AR_NOW);

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
    AccountingPeriod::create(['name' => 'Test', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);

    $this->farmerUser = User::factory()->create();
    $this->farmerUser->assignRole('farmer');
    $this->profile = FarmerProfile::factory()->create(['user_id' => $this->farmerUser->id]);
});

afterEach(fn() => Carbon::setTestNow());

// a record as the phone sends it; the phone's own time and the record's own date are set apart
function arRecord(array $over = []): array
{
    return array_merge([
        'uuid' => (string) Str::uuid(),
        'template' => test()->template->id,
        'farmer' => test()->profile->uuid,
        'amount' => '100',
        'settlement_account_id' => test()->cash->id,
        'event_date' => '2026-03-10',
        'device_created_at' => '2026-03-10T11:59:00Z',
    ], $over);
}

function arSend(array ...$records): array
{
    return test()->actingAs(test()->farmerUser)->postJson('/sync/submissions', ['records' => $records])->assertOk()->json('results');
}

function arRow(string $uuid): SyncSubmission
{
    return SyncSubmission::where('client_uuid', $uuid)->firstOrFail();
}

describe('the age rule', function () {
    it('rejects a record whose own date is more than 7 days old, with the code, the approved text and no ledger entry', function () {
        $record = arRecord(['event_date' => '2026-03-02']);

        $result = arSend($record)[0];

        $row = arRow($record['uuid']);
        expect($result['status'])->toBe('rejected')
            ->and($result['reason'])->toBe(AR_TOO_OLD)
            ->and($row->status)->toBe('rejected')
            ->and($row->reason_code)->toBe('too_old')
            ->and($row->reason)->toBe(AR_TOO_OLD)
            ->and($row->transaction_id)->toBeNull()
            ->and(Transaction::count())->toBe(0)
            ->and(JournalEntry::count())->toBe(0);
    });

    it('rejects a record whose phone time is more than 7 days old, the same way', function () {
        $record = arRecord(['device_created_at' => '2026-03-03T11:59:59Z']);

        $result = arSend($record)[0];

        expect($result['status'])->toBe('rejected')
            ->and($result['reason'])->toBe(AR_TOO_OLD)
            ->and(arRow($record['uuid'])->reason_code)->toBe('too_old')
            ->and(Transaction::count())->toBe(0);
    });

    it('accepts a record whose own date is exactly 7 days back, and rejects 8', function () {
        $edge = arRecord(['event_date' => '2026-03-03']);
        $over = arRecord(['event_date' => '2026-03-02']);

        $results = arSend($edge, $over);

        expect($results[0]['status'])->toBe('accepted')->and($results[1]['status'])->toBe('rejected');
    });

    it('accepts a phone time exactly 7 days old, and rejects one second more', function () {
        $edge = arRecord(['device_created_at' => '2026-03-03T12:00:00Z']);
        $over = arRecord(['device_created_at' => '2026-03-03T11:59:59Z']);

        $results = arSend($edge, $over);

        expect($results[0]['status'])->toBe('accepted')->and($results[1]['status'])->toBe('rejected');
    });
});

describe('the clock rule', function () {
    it('rejects a phone time more than 10 minutes ahead, with the code and the approved text', function () {
        $record = arRecord(['device_created_at' => '2026-03-10T12:10:01Z']);

        $result = arSend($record)[0];

        expect($result['status'])->toBe('rejected')
            ->and($result['reason'])->toBe(AR_CLOCK_AHEAD)
            ->and(arRow($record['uuid'])->reason_code)->toBe('clock_ahead')
            ->and(Transaction::count())->toBe(0);
    });

    it('accepts a phone time 10 minutes ahead or less', function () {
        $results = arSend(arRecord(['device_created_at' => '2026-03-10T12:10:00Z']), arRecord(['device_created_at' => '2026-03-10T12:05:00Z']));

        expect(array_column($results, 'status'))->toBe(['accepted', 'accepted']);
    });

    it('leaves the check on the record\'s own date as it was', function () {
        $result = arSend(arRecord(['event_date' => '2026-03-11']))[0];

        expect($result['status'])->toBe('needs_fixing')->and($result['reason'])->toBe('That date has not happened yet.');
    });

    it('uses the server\'s time and never a time the phone calls now', function () {
        // a phone that is wrong by hours says so in its own time stamp, whatever else it claims
        $record = arRecord(['device_created_at' => '2026-03-10T15:00:00Z', 'now' => '2026-03-10T15:00:00Z']);

        expect(arSend($record)[0]['status'])->toBe('rejected');
    });
});

it('accepts a fresh record as before', function () {
    $result = arSend(arRecord())[0];

    expect($result['status'])->toBe('accepted')->and($result['reference'])->not->toBeNull()->and(Transaction::count())->toBe(1);
});

describe('a record seen before comes first', function () {
    it('returns the original result for a late retry of an accepted record', function () {
        $record = arRecord();
        $first = arSend($record)[0];

        Carbon::setTestNow('2026-04-20 12:00:00');
        $again = arSend($record)[0];

        expect($again)->toBe($first)->and($again['status'])->toBe('accepted')->and(Transaction::count())->toBe(1);
    });

    it('keeps a rejected record rejected when sent again, with no second audit entry or record', function () {
        $record = arRecord(['event_date' => '2026-03-02']);
        $first = arSend($record)[0];

        $again = arSend($record)[0];
        Carbon::setTestNow('2026-03-11 12:00:00');
        $later = arSend($record)[0];

        expect($again)->toBe($first)->and($later)->toBe($first)
            ->and(SyncSubmission::count())->toBe(1)
            ->and(AuditLog::where('action', 'sync.submission_auto_rejected')->count())->toBe(1);
    });

    it('does not turn a record that needed a fix into a rejection by age', function () {
        $record = arRecord(['settlement_account_id' => 999999]);
        $first = arSend($record)[0];
        expect($first['status'])->toBe('needs_fixing');

        Carbon::setTestNow('2026-04-20 12:00:00');

        expect(arSend($record)[0])->toBe($first);
    });
});

describe('a rejection by these rules', function () {
    it('is a system decision: no reviewer, one audit entry with no user and the code', function () {
        $record = arRecord(['event_date' => '2026-03-02']);

        arSend($record);

        $row = arRow($record['uuid']);
        $entries = AuditLog::where('action', 'sync.submission_auto_rejected')->get();
        expect($row->reviewed_by)->toBeNull()
            ->and($row->reviewed_at)->toBeNull()
            ->and($entries)->toHaveCount(1)
            ->and($entries[0]->user_id)->toBeNull()
            ->and($entries[0]->auditable_id)->toBe($row->id)
            ->and($entries[0]->new_values['code'])->toBe('too_old');
    });

    it('never goes to admin review', function () {
        arSend(arRecord(['event_date' => '2026-03-02']));
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->get('/admin/sync-submissions')->assertInertia(fn($page) => $page->has('submissions.data', 0));
    });

    it('is judged before a record would be held, so an old record from a blocked account is rejected, not held', function () {
        $this->farmerUser->forceFill(['is_active' => false])->save();

        $result = arSend(arRecord(['event_date' => '2026-03-02']))[0];

        expect($result['status'])->toBe('rejected');
    });

    it('sinks nothing else in the same batch', function () {
        $results = arSend(arRecord(['event_date' => '2026-03-02']), arRecord());

        expect(array_column($results, 'status'))->toBe(['rejected', 'accepted']);
    });

    it('tells the farmer with the notice a rejection already sends', function () {
        arSend(arRecord(['event_date' => '2026-03-02']));

        expect(Notification::where('user_id', $this->farmerUser->id)->where('kind', 'sync.rejected')->pluck('message')->all())
            ->toBe(['A record was not accepted. ' . AR_TOO_OLD]);
    });

    it('shows under Rejected with the reason, and the farmer can dismiss it', function () {
        $record = arRecord(['device_created_at' => '2026-03-10T12:30:00Z']);
        arSend($record);

        $rows = $this->actingAs($this->farmerUser)->get('/my-records')->viewData('page')['props']['flagged'];
        expect($rows)->toHaveCount(1)
            ->and($rows[0]['status'])->toBe('rejected')
            ->and($rows[0]['reason'])->toBe(AR_CLOCK_AHEAD);

        $this->actingAs($this->farmerUser)->postJson("/my-records/rejected/{$rows[0]['uuid']}/dismiss")->assertOk();

        expect($this->actingAs($this->farmerUser)->get('/my-records')->viewData('page')['props']['flagged'])->toBe([]);
    });
});

describe('what is left alone', function () {
    it('does not apply to health reports', function () {
        $farmType = FarmType::factory()->withCategory(FarmTypeCategory::create(['name' => 'Livestock']))->create();
        $unit = FarmUnit::factory()->create(['farmer_profile_id' => $this->profile->id, 'farm_type_id' => $farmType->id]);

        $result = arSend([
            'type' => 'health_report', 'uuid' => (string) Str::uuid(), 'farmer' => $this->profile->uuid, 'farm_unit_id' => $unit->id,
            'description' => 'Weak birds', 'event_date' => '2025-01-01', 'device_created_at' => '2025-01-01T00:00:00Z',
        ])[0];

        expect($result['status'])->toBe('accepted');
    });

    it('does not apply to the online form', function () {
        $this->actingAs($this->farmerUser)->post('/my-records', [
            'transaction_template_id' => $this->template->id, 'amount' => '100', 'settlement_account_id' => $this->cash->id,
            'transaction_date' => '2026-01-15', 'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect();

        expect(Transaction::count())->toBe(1);
    });
});

describe('the numbers', function () {
    it('come from config, 7 days and 10 minutes to begin with', function () {
        expect(config('sync_rules.max_age_days'))->toBe(7)->and(config('sync_rules.max_clock_ahead_minutes'))->toBe(10);
    });

    it('follow a changed age limit', function () {
        config(['sync_rules.max_age_days' => 30]);

        expect(arSend(arRecord(['event_date' => '2026-03-02', 'device_created_at' => '2026-03-02T09:00:00Z']))[0]['status'])->toBe('accepted');
    });

    it('follow a changed clock limit', function () {
        config(['sync_rules.max_clock_ahead_minutes' => 1]);

        expect(arSend(arRecord(['device_created_at' => '2026-03-10T12:02:00Z']))[0]['status'])->toBe('rejected');
    });
});
