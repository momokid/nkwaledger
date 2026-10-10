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
use Illuminate\Support\Facades\DB;
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

    // inactive accounts land held, which is what the admin routes act on
    $farmer = function () {
        $account = User::factory()->create(['is_active' => false]);
        $account->assignRole('farmer');

        return [$account, FarmerProfile::factory()->create(['user_id' => $account->id])];
    };

    [$this->userA, $this->profileA] = $farmer();
    [$this->userB, $this->profileB] = $farmer();
});

function publicSync(User $user, FarmerProfile $profile, string $clientUuid): SyncSubmission
{
    test()->actingAs($user)->postJson('/sync/submissions', ['records' => [[
        'uuid' => $clientUuid, 'template' => test()->template->id, 'farmer' => $profile->uuid, 'amount' => '100',
        'settlement_account_id' => test()->cash->id, 'event_date' => now()->toDateString(), 'device_created_at' => now()->toIso8601String(),
    ]]])->assertOk();

    return SyncSubmission::where('user_id', $user->id)->where('client_uuid', $clientUuid)->firstOrFail();
}

test('a new submission gets its own unique uuid, separate from the client uuid', function () {
    $client = (string) Str::uuid();
    $a = publicSync($this->userA, $this->profileA, $client);
    $b = publicSync($this->userB, $this->profileB, $client);

    expect(Str::isUuid($a->uuid))->toBeTrue()->and(Str::isUuid($b->uuid))->toBeTrue()
        ->and($a->uuid)->not->toBe($b->uuid)
        ->and($a->uuid)->not->toBe($client);
});

test('approve and reject work by the public uuid, and two users sharing a client uuid stay apart', function () {
    $client = (string) Str::uuid();
    $a = publicSync($this->userA, $this->profileA, $client);
    $b = publicSync($this->userB, $this->profileB, $client);

    $this->actingAs($this->admin)->postJson("/admin/sync-submissions/{$a->uuid}/approve")->assertOk();

    expect($a->fresh()->status)->toBe('accepted')->and($b->fresh()->status)->toBe('held_for_review');

    $this->actingAs($this->admin)->postJson("/admin/sync-submissions/{$b->uuid}/reject", ['reason' => 'No.'])->assertOk();

    expect($b->fresh()->status)->toBe('rejected')->and($a->fresh()->status)->toBe('accepted')->and(Transaction::count())->toBe(1);
});

test('the numeric id and a malformed value are a 404 on both routes', function () {
    $row = publicSync($this->userA, $this->profileA, (string) Str::uuid());

    foreach ([(string) $row->id, 'not-a-uuid', str_repeat('-', 36)] as $value) {
        $this->actingAs($this->admin)->postJson("/admin/sync-submissions/{$value}/approve")->assertNotFound();
        $this->actingAs($this->admin)->postJson("/admin/sync-submissions/{$value}/reject", ['reason' => 'x'])->assertNotFound();
    }

    expect($row->fresh()->status)->toBe('held_for_review');
});

test('a uuid that does not exist is a 404', function () {
    $missing = (string) Str::uuid();

    $this->actingAs($this->admin)->postJson("/admin/sync-submissions/{$missing}/approve")->assertNotFound();
    $this->actingAs($this->admin)->postJson("/admin/sync-submissions/{$missing}/reject", ['reason' => 'x'])->assertNotFound();
});

test('non-admins still get 403 by uuid', function () {
    $row = publicSync($this->userA, $this->profileA, (string) Str::uuid());

    $this->actingAs($this->userB)->postJson("/admin/sync-submissions/{$row->uuid}/approve")->assertForbidden();
    $this->actingAs($this->userB)->postJson("/admin/sync-submissions/{$row->uuid}/reject", ['reason' => 'x'])->assertForbidden();
});

test('the migration gives rows that already existed a unique uuid, and runs up and down', function () {
    $migration = require database_path('migrations/' . collect(glob(database_path('migrations/*_add_public_uuid_to_sync_submissions_table.php')))->map('basename')->first());

    publicSync($this->userA, $this->profileA, (string) Str::uuid());
    publicSync($this->userB, $this->profileB, (string) Str::uuid());

    $migration->down();
    expect(Schema::hasColumn('sync_submissions', 'uuid'))->toBeFalse()->and(DB::table('sync_submissions')->count())->toBe(2);

    $migration->up();

    $uuids = DB::table('sync_submissions')->pluck('uuid');
    $unique = collect(Schema::getIndexes('sync_submissions'))->where('unique', true)->pluck('columns')->map(fn($c) => implode(',', $c));

    expect($uuids)->toHaveCount(2)->and($uuids->unique())->toHaveCount(2)
        ->and($uuids->every(fn($u) => Str::isUuid($u)))->toBeTrue()
        ->and($unique)->toContain('uuid');
});
