<?php

use App\Models\AuditLog;
use App\Models\FarmType;
use App\Models\User;
use Illuminate\Database\QueryException;

test('an entry records who acted and what they did', function () {
    $user = User::factory()->create();

    $entry = AuditLog::create([
        'user_id' => $user->id,
        'action'  => 'created',
    ]);

    expect($entry->user_id)->toBe($user->id);
    expect($entry->action)->toBe('created');
});

test('an entry points at the record it touched', function () {
    $farmType = FarmType::factory()->create();

    $entry = AuditLog::create([
        'action'         => 'updated',
        'auditable_type' => FarmType::class,
        'auditable_id'   => $farmType->id,
    ]);

    expect($entry->auditable->is($farmType))->toBeTrue();
});

test('an entry belongs to the person who made it', function () {
    $user = User::factory()->create();

    $entry = AuditLog::create(['user_id' => $user->id, 'action' => 'created']);

    expect($entry->user->is($user))->toBeTrue();
});

// a system action has no person behind it
test('an entry can record a system action with no user', function () {
    $entry = AuditLog::create(['user_id' => null, 'action' => 'verification.expired']);

    expect($entry->user_id)->toBeNull();
});

// a failed login touches no record, so the reference stays empty
test('an entry can record an event with no record behind it', function () {
    $entry = AuditLog::create(['action' => 'login.failed', 'ip_address' => '41.66.0.1']);

    expect($entry->auditable_id)->toBeNull();
    expect($entry->ip_address)->toBe('41.66.0.1');
});

test('before and after values come back as arrays', function () {
    $entry = AuditLog::create([
        'action'     => 'updated',
        'old_values' => ['name' => 'Maize'],
        'new_values' => ['name' => 'Yellow Maize'],
    ]);

    expect($entry->fresh()->old_values)->toBe(['name' => 'Maize']);
    expect($entry->fresh()->new_values)->toBe(['name' => 'Yellow Maize']);
});

test('an entry records where the action came from', function () {
    $entry = AuditLog::create([
        'action'     => 'created',
        'ip_address' => '41.66.0.1',
        'user_agent' => 'Mozilla/5.0',
    ]);

    expect($entry->ip_address)->toBe('41.66.0.1');
    expect($entry->user_agent)->toBe('Mozilla/5.0');
});

test('an entry is stamped with when it happened', function () {
    $entry = AuditLog::create(['action' => 'created']);

    expect($entry->created_at)->not->toBeNull();
});

// a log that can be edited is not a log
test('an entry cannot be changed once written', function () {
    $entry = AuditLog::create(['action' => 'created']);

    expect(fn() => $entry->update(['action' => 'deleted']))
        ->toThrow(RuntimeException::class);
});

test('an entry cannot be deleted', function () {
    $entry = AuditLog::create(['action' => 'created']);

    expect(fn() => $entry->delete())->toThrow(RuntimeException::class);
});

test('an action is required', function () {
    expect(fn() => AuditLog::create(['user_id' => null]))
        ->toThrow(QueryException::class);
});

// the table will outgrow every other one, so reading it by date has to stay cheap
test('entries are ordered newest first by default', function () {
    $older = AuditLog::create(['action' => 'created']);
    $older->forceFill(['created_at' => now()->subDay()])->saveQuietly();

    $newer = AuditLog::create(['action' => 'updated']);

    expect(AuditLog::query()->first()->id)->toBe($newer->id);
});
