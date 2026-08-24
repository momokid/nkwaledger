<?php

use App\Models\AuditLog;
use App\Models\FarmType;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    $this->admin->givePermissionTo('audit.view');
});

test('a guest is redirected to login', function () {
    $this->get('/admin/audit')->assertRedirect('/login');
});

test('a user without audit.view is forbidden', function () {
    $agent = User::factory()->create();
    $agent->assignRole('agent');

    $this->actingAs($agent)->get('/admin/audit')->assertForbidden();
});

test('an authorized admin sees the page', function () {
    $this->actingAs($this->admin)->get('/admin/audit')
        ->assertOk()
        ->assertInertia(fn($page) => $page->component('Admin/Audit/Index'));
});

test('entries are listed', function () {
    FarmType::factory()->count(3)->create();

    $this->actingAs($this->admin)->get('/admin/audit')
        ->assertInertia(fn($page) => $page->has('entries.data'));
});

test('each row carries who acted and what they did', function () {
    $this->actingAs($this->admin);

    FarmType::factory()->create(['name' => 'Maize']);

    $this->get('/admin/audit')
        ->assertInertia(
            fn($page) => $page
                ->where('entries.data.0.action', 'created')
                ->where('entries.data.0.user_name', "{$this->admin->surname} {$this->admin->first_name}")
        );
});

// the account may be gone, but the entry stays
test('an entry with no user still renders', function () {
    AuditLog::create(['action' => 'verification.expired']);

    $this->actingAs($this->admin)->get('/admin/audit')
        ->assertInertia(fn($page) => $page->where('entries.data.0.user_name', null));
});

test('the record touched is named in plain words', function () {
    $farmType = FarmType::factory()->create();

    $this->actingAs($this->admin)->get('/admin/audit')
        ->assertInertia(fn($page) => $page->where('entries.data.0.record', 'Farm Type'));
});

test('before and after values reach the page', function () {
    $farmType = FarmType::factory()->create(['name' => 'Maize']);
    $farmType->update(['name' => 'Yellow Maize']);

    $this->actingAs($this->admin)->get('/admin/audit')
        ->assertInertia(
            fn($page) => $page
                ->where('entries.data.0.old_values.name', 'Maize')
                ->where('entries.data.0.new_values.name', 'Yellow Maize')
        );
});

test('the newest entry is first', function () {
    $first = FarmType::factory()->create(['name' => 'Maize']);
    $second = FarmType::factory()->create(['name' => 'Cassava']);

    $this->actingAs($this->admin)->get('/admin/audit')
        ->assertInertia(fn($page) => $page->where('entries.data.0.new_values.name', 'Cassava'));
});

test('the list is paginated', function () {
    FarmType::factory()->count(30)->create();

    $this->actingAs($this->admin)->get('/admin/audit')
        ->assertInertia(fn($page) => $page->has('entries.data', 25));
});

test('entries can be filtered by action', function () {
    $farmType = FarmType::factory()->create();
    $farmType->update(['name' => 'Changed']);

    $this->actingAs($this->admin)->get('/admin/audit?action=updated')
        ->assertInertia(
            fn($page) => $page
                ->has('entries.data', 1)
                ->where('entries.data.0.action', 'updated')
        );
});

test('entries can be filtered by person', function () {
    $other = User::factory()->create();

    $this->actingAs($other);
    FarmType::factory()->create();

    $this->actingAs($this->admin);
    FarmType::factory()->create();

    $this->get("/admin/audit?user_id={$other->id}")
        ->assertInertia(fn($page) => $page->has('entries.data', 1));
});

test('entries can be filtered by date', function () {
    $old = AuditLog::create(['action' => 'created']);
    $old->forceFill(['created_at' => now()->subMonth()])->saveQuietly();

    AuditLog::create(['action' => 'updated']);

    $this->actingAs($this->admin)->get('/admin/audit?from=' . now()->subWeek()->toDateString())
        ->assertInertia(fn($page) => $page->where('entries.data.0.action', 'updated'));
});

// the page lists actions and people so the filters can be chosen, not typed
test('the page ships the filter choices', function () {
    FarmType::factory()->create();

    $this->actingAs($this->admin)->get('/admin/audit')
        ->assertInertia(fn($page) => $page->has('actions')->has('people'));
});

// nothing on this page may change anything
test('there is no way to change an entry', function () {
    $entry = AuditLog::create(['action' => 'created']);

    $this->actingAs($this->admin)->delete("/admin/audit/{$entry->id}")->assertNotFound();
    $this->actingAs($this->admin)->put("/admin/audit/{$entry->id}")->assertNotFound();
});
