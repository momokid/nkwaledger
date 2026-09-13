<?php

use App\Models\User;
use App\Session\RoleAwareDatabaseSessionHandler;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->handler = new RoleAwareDatabaseSessionHandler(
        DB::connection(),
        'sessions',
        config('session.lifetime'),
        app(),
    );
});

function seedSessionRow(string $id, int $userId, int $minutesAgo): void
{
    DB::table('sessions')->insert([
        'id' => $id,
        'user_id' => $userId,
        'payload' => base64_encode(serialize(['foo' => 'bar'])),
        'last_activity' => now()->subMinutes($minutesAgo)->timestamp,
    ]);
}

it('keeps a farmer session alive past the old default lifetime', function () {
    $farmer = User::factory()->create();
    $farmer->assignRole('farmer');

    seedSessionRow('farmer-session', $farmer->id, config('session.lifetime') + 60);

    expect($this->handler->read('farmer-session'))->not->toBe('');
});

it('still expires an admin session at the old default lifetime', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    seedSessionRow('admin-session', $admin->id, config('session.lifetime') + 60);

    expect($this->handler->read('admin-session'))->toBe('');
});

it('eventually expires a farmer session once past the extended lifetime', function () {
    $farmer = User::factory()->create();
    $farmer->assignRole('farmer');

    seedSessionRow('farmer-session-old', $farmer->id, config('session.extended_lifetime') + 60);

    expect($this->handler->read('farmer-session-old'))->toBe('');
});

it('does not let garbage collection sweep away a farmer session before its extended lifetime', function () {
    $farmer = User::factory()->create();
    $farmer->assignRole('farmer');

    seedSessionRow('farmer-session-gc', $farmer->id, config('session.lifetime') + 60);

    $this->handler->gc(config('session.lifetime') * 60);

    expect(DB::table('sessions')->where('id', 'farmer-session-gc')->exists())->toBeTrue();
});

it('lets garbage collection sweep an admin session once past the old default lifetime', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    seedSessionRow('admin-session-gc', $admin->id, config('session.lifetime') + 60);

    $this->handler->gc(config('session.lifetime') * 60);

    expect(DB::table('sessions')->where('id', 'admin-session-gc')->exists())->toBeFalse();
});

it('lets garbage collection sweep a farmer session once past its extended lifetime', function () {
    $farmer = User::factory()->create();
    $farmer->assignRole('farmer');

    seedSessionRow('farmer-session-gc-old', $farmer->id, config('session.extended_lifetime') + 60);

    $this->handler->gc(config('session.lifetime') * 60);

    expect(DB::table('sessions')->where('id', 'farmer-session-gc-old')->exists())->toBeFalse();
});
