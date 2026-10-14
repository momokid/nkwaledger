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
use App\Models\UserPermissionDenial;
use App\Services\NavigationAccessService;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->submitter = User::factory()->create(['surname' => 'Mensah', 'first_name' => 'Ama']);
    $this->submitter->assignRole('farmer');
    $this->farmerUser = User::factory()->create(['surname' => 'Owusu', 'first_name' => 'Kofi']);
    $this->profile = FarmerProfile::factory()->create(['user_id' => $this->farmerUser->id]);

    $class = LedgerClass::create(['name' => 'Dr']);
    $sub = LedgerSubcategory::create(['category_id' => LedgerCategory::create(['name' => 'Assets', 'class_id' => $class->id])->id, 'name' => 'Money']);
    $control = LedgerControl::create(['name' => 'General']);
    $type = LedgerType::create(['name' => 'GL']);
    $account = fn(string $name) => LedgerAccount::create(['name' => $name, 'control_id' => $control->id, 'subcategory_id' => $sub->id, 'type_id' => $type->id]);

    $this->template = TransactionTemplate::create([
        'name' => 'I sold crops', 'slug' => 'crop_sale', 'transaction_type' => 'INCOME',
        'debit_account_id' => $account('Cash')->id, 'credit_account_id' => $account('Sales')->id, 'settlement_side' => 'none',
    ]);
});

function heldRow(array $o = []): SyncSubmission
{
    return SyncSubmission::create($o + [
        'client_uuid' => (string) Str::uuid(),
        'user_id' => test()->submitter->id,
        'farmer_profile_id' => test()->profile->id,
        'payload' => ['template' => test()->template->id, 'amount' => '100', 'event_date' => '2026-03-02'],
        'device_date' => '2026-03-02',
        'received_at' => now(),
        'status' => 'held_for_review',
        'reason' => 'This account cannot record right now, so an admin will look at it.',
    ]);
}

function heldPage(User $user, string $query = '')
{
    return test()->actingAs($user)->get('/admin/sync-submissions' . $query);
}

// the props this page passes itself, not the layout's shared ones
function pageProps($response): array
{
    return Arr::only($response->viewData('page')['props'], ['submissions']);
}

test('an admin sees held and needs-a-fix submissions only', function () {
    $held = heldRow(['received_at' => now()->subMinute()]);
    $fix = heldRow(['status' => 'needs_fixing']);
    foreach (['accepted', 'rejected', 'superseded'] as $status) {
        heldRow(['status' => $status]);
    }

    $response = heldPage($this->admin)->assertOk()->assertInertia(fn($page) => $page->component('Admin/SyncSubmissions/Index')->has('submissions.data', 2));

    expect(collect(pageProps($response)['submissions']['data'])->pluck('uuid')->sort()->values()->all())->toBe(collect([$held->uuid, $fix->uuid])->sort()->values()->all());
});

test('newest received first and 20 to a page', function () {
    foreach (range(1, 25) as $n) {
        heldRow(['received_at' => now()->subMinutes(30 - $n)]);
    }

    $first = heldPage($this->admin)->assertInertia(fn($page) => $page->has('submissions.data', 20));
    $dates = collect(pageProps($first)['submissions']['data'])->pluck('received_at');

    expect($dates->sortDesc()->values()->all())->toBe($dates->all());

    heldPage($this->admin, '?page=2')->assertInertia(fn($page) => $page->has('submissions.data', 5));
});

test('the props carry the row uuid and no numeric id of any model', function () {
    $row = heldRow();
    $props = pageProps(heldPage($this->admin));

    $ids = [];
    array_walk_recursive($props, function ($value, $key) use (&$ids) {
        if (preg_match('/(^|_)id$/', (string) $key)) {
            $ids[] = $key;
        }
    });

    expect($ids)->toBe([])->and($props['submissions']['data'][0]['uuid'])->toBe($row->uuid);

    foreach ([$row->id, $this->submitter->id, $this->profile->id, $this->template->id] as $id) {
        expect(json_encode($props))->not->toContain('"id":' . $id);
    }

    expect(json_encode($props))->not->toContain($this->farmerUser->phone)->not->toContain($this->submitter->phone);
});

test('a row shows the farmer, submitter, record, amount, dates and reason', function () {
    heldRow();
    $row = pageProps(heldPage($this->admin))['submissions']['data'][0];

    expect($row['farmer'])->toBe('Owusu Kofi')
        ->and($row['submitted_by'])->toBe('Mensah Ama')
        ->and($row['record'])->toBe('I sold crops')
        ->and($row['amount'])->toBe('GHS 100.00')
        ->and($row['event_date'])->toBe('2026-03-02')
        ->and($row['received_at'])->not->toBeNull()
        ->and($row['reason'])->toBe('This account cannot record right now, so an admin will look at it.');
});

test('a missing template shows Unknown', function () {
    heldRow(['payload' => ['template' => 999999, 'amount' => '100', 'event_date' => '2026-03-02']]);

    expect(pageProps(heldPage($this->admin))['submissions']['data'][0]['record'])->toBe('Unknown');
});

test('the query count does not grow with the rows', function () {
    $count = function () {
        DB::flushQueryLog();
        DB::enableQueryLog();
        heldPage($this->admin)->assertOk();

        return count(DB::getQueryLog());
    };

    heldRow();
    heldPage($this->admin)->assertOk(); // first request warms the permission cache, which is not about rows
    $one = $count();

    foreach (range(1, 19) as $n) {
        heldRow();
    }

    expect($count())->toBe($one);
});

test('non-admins get 403 and a guest goes to login', function () {
    $this->actingAs($this->submitter)->get('/admin/sync-submissions')->assertForbidden();

    $agent = User::factory()->create();
    $agent->assignRole('agent');
    $this->actingAs($agent)->get('/admin/sync-submissions')->assertForbidden();

    auth()->logout();
    $this->get('/admin/sync-submissions')->assertRedirect('/login');
});

test('the menu entry shows for an admin with the permission and for nobody else', function () {
    $leaves = fn(User $user) => collect(app(NavigationAccessService::class)->adminMenu($user))
        ->flatMap(fn($entry) => $entry['children'] ?? [$entry])->pluck('routeName');

    expect($leaves($this->admin))->toContain('admin.sync-submissions.index');

    $denied = User::factory()->create();
    $denied->assignRole('admin');
    UserPermissionDenial::create(['user_id' => $denied->id, 'denied_by' => $this->admin->id, 'permission_id' => Permission::where('name', 'sync-submissions.view')->value('id')]);

    expect($leaves($denied))->not->toContain('admin.sync-submissions.index')
        ->and(app(NavigationAccessService::class)->adminMenu($this->submitter))->toBe([]);
});

test('with nothing held the page has no rows', function () {
    heldRow(['status' => 'accepted']);

    heldPage($this->admin)->assertOk()->assertInertia(fn($page) => $page->has('submissions.data', 0));
});

test('the view permission is in config and seeded for admins', function () {
    expect(config('permissions.modules.sync-submissions.actions'))->toHaveKey('view')
        ->and(config('permissions.defaults.admin'))->toContain('sync-submissions.view')
        ->and(Role::findByName('admin')->hasPermissionTo('sync-submissions.view'))->toBeTrue();
});
