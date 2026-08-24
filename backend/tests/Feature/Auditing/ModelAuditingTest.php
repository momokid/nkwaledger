<?php

use App\Models\AuditLog;
use App\Models\FarmType;
use App\Models\User;

beforeEach(function () {
    $this->actor = User::factory()->create();
    $this->actingAs($this->actor);
});

test('creating a record is written to the log', function () {
    $farmType = FarmType::factory()->create(['name' => 'Maize']);

    $entry = AuditLog::where('auditable_id', $farmType->id)->first();

    expect($entry)->not->toBeNull();
    expect($entry->action)->toBe('created');
    expect($entry->auditable_type)->toBe(FarmType::class);
});

test('the person who acted is recorded', function () {
    FarmType::factory()->create();

    expect(AuditLog::first()->user_id)->toBe($this->actor->id);
});

test('a new record keeps its values but has nothing before it', function () {
    FarmType::factory()->create(['name' => 'Cassava']);

    $entry = AuditLog::first();

    expect($entry->new_values['name'])->toBe('Cassava');
    expect($entry->old_values)->toBeNull();
});

test('an update records what changed', function () {
    $farmType = FarmType::factory()->create(['name' => 'Maize']);

    $farmType->update(['name' => 'Yellow Maize']);

    $entry = AuditLog::where('action', 'updated')->first();

    expect($entry->old_values['name'])->toBe('Maize');
    expect($entry->new_values['name'])->toBe('Yellow Maize');
});

// storing every column would bloat a table that already outgrows the rest
test('an update records only the fields that changed', function () {
    $farmType = FarmType::factory()->create(['name' => 'Maize']);

    $farmType->update(['name' => 'Yellow Maize']);

    $entry = AuditLog::where('action', 'updated')->first();

    expect(array_keys($entry->new_values))->toBe(['name']);
});

test('saving with no change writes nothing', function () {
    $farmType = FarmType::factory()->create();

    $before = AuditLog::count();

    $farmType->update(['name' => $farmType->name]);

    expect(AuditLog::count())->toBe($before);
});

test('a deletion is recorded with what was there', function () {
    $farmType = FarmType::factory()->create(['name' => 'Maize']);

    $farmType->delete();

    $entry = AuditLog::where('action', 'deleted')->first();

    expect($entry)->not->toBeNull();
    expect($entry->old_values['name'])->toBe('Maize');
});

test('the request address is recorded', function () {
    FarmType::factory()->create();

    expect(AuditLog::first()->ip_address)->not->toBeNull();
});

// a scheduled command has no person behind it
test('an action with nobody signed in is still recorded', function () {
    auth()->logout();

    FarmType::factory()->create();

    $entry = AuditLog::first();

    expect($entry)->not->toBeNull();
    expect($entry->user_id)->toBeNull();
});

// writing an audit row must never itself be audited
test('the log does not record itself', function () {
    FarmType::factory()->create();

    expect(AuditLog::where('auditable_type', AuditLog::class)->exists())->toBeFalse();
});
