<?php

use App\Contracts\SmsProvider;
use App\Models\FarmerProfile;
use App\Models\OtpCode;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

const LOCKED_MESSAGE = 'Your account is locked. Please contact your agent.';

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    // the real session table, not the in-memory one the tests normally use
    config(['session.driver' => 'database']);
    app('session')->forgetDrivers();

    $this->farmerUser = User::factory()->unverified()->create(['phone' => '0244000811']);
    $this->farmerUser->assignRole('farmer');
    $this->profile = FarmerProfile::factory()->create(['user_id' => $this->farmerUser->id]);
});

function lockedSession(User $user): string
{
    $id = Str::random(40);

    DB::table('sessions')->insert([
        'id' => $id,
        'user_id' => $user->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'test phone',
        'payload' => base64_encode(json_encode(['_token' => Str::random(40), Auth::guard('web')->getName() => $user->id])),
        'last_activity' => time(),
    ]);

    return $id;
}

function lockedPhone(array $cookies)
{
    app('auth')->forgetGuards();
    app('session.store')->flush();

    return test()->withCredentials()->withCookies($cookies);
}

function lockAccount(User $user): void
{
    $user->forceFill(['locked_at' => now()])->save();
}

test('a locked farmer typing the right password is told so, and gets no session', function () {
    lockAccount($this->farmerUser);

    $this->post('/login', ['identifier' => '0244000811', 'password' => 'Password@123'])
        ->assertSessionHasErrors(['identifier' => LOCKED_MESSAGE]);

    $this->assertGuest();
    expect(DB::table('sessions')->where('user_id', $this->farmerUser->id)->count())->toBe(0);
});

test('a wrong password for a locked farmer says the same as for anyone, so the lock is not revealed', function () {
    lockAccount($this->farmerUser);

    $this->post('/login', ['identifier' => '0244000811', 'password' => 'not-the-password'])
        ->assertSessionHasErrors(['identifier' => trans('auth.failed')]);
});

test('a correct code does not sign a locked farmer in, and the phone stays unverified', function () {
    lockAccount($this->farmerUser);
    OtpCode::create(['identifier' => '0244000811', 'code' => Hash::make('123456'), 'type' => 'login', 'expires_at' => now()->addMinutes(5)]);

    $this->withSession(['auth.login_identifier' => '0244000811', 'auth.otp_type' => 'login'])
        ->post('/verify-otp', ['code' => '123456'])
        ->assertSessionHasErrors(['code' => LOCKED_MESSAGE]);

    $this->assertGuest();
    expect($this->farmerUser->fresh()->phone_verified_at)->toBeNull();
});

test('asking for a code looks the same for a locked number as for any other', function () {
    lockAccount($this->farmerUser);

    $this->post('/login/otp', ['phone' => '0244000811'])->assertRedirect('/verify-otp');
});

test('a session that outlived the lock is refused on every kind of request', function () {
    $session = lockedSession($this->farmerUser);
    lockAccount($this->farmerUser);
    $name = config('session.cookie');

    lockedPhone([$name => $session])->getJson('/auth/check')->assertStatus(302);
    lockedPhone([$name => $session])->postJson('/sync/submissions', ['records' => []])->assertUnauthorized();
    lockedPhone([$name => $session])->get('/my-farm')->assertRedirect('/login');
});

test('a locked farmer cannot ask for or confirm a PIN reset code', function () {
    $session = lockedSession($this->farmerUser);
    lockAccount($this->farmerUser);
    $name = config('session.cookie');
    $before = count(app(SmsProvider::class)->sent ?? []);

    lockedPhone([$name => $session])->postJson('/pin-reset/send')->assertUnauthorized();
    lockedPhone([$name => $session])->postJson('/pin-reset/confirm', ['code' => '123456'])->assertUnauthorized();

    expect(count(app(SmsProvider::class)->sent ?? []))->toBe($before)
        ->and(OtpCode::where('type', 'pin_reset')->count())->toBe(0);
});

test('a remember-me cookie stops working at the lock', function () {
    $this->farmerUser->forceFill(['remember_token' => Str::random(60), 'locked_at' => now()])->save();
    $recaller = "{$this->farmerUser->id}|{$this->farmerUser->remember_token}|{$this->farmerUser->password}";

    lockedPhone([Auth::guard('web')->getRecallerName() => $recaller])->getJson('/auth/check')->assertStatus(302);
});

test('after the unlock the farmer signs in normally and sync works again', function () {
    lockAccount($this->farmerUser);
    $this->post('/login', ['identifier' => '0244000811', 'password' => 'Password@123'])->assertSessionHasErrors('identifier');

    $this->farmerUser->forceFill(['locked_at' => null, 'locked_by' => null])->save();

    app('auth')->forgetGuards();
    $this->post('/login', ['identifier' => '0244000811', 'password' => 'Password@123'])->assertRedirect()->assertSessionHasNoErrors();

    $this->farmerUser->forceFill(['phone_verified_at' => now()])->save();
    $fresh = lockedSession($this->farmerUser);
    lockedPhone([config('session.cookie') => $fresh])->postJson('/sync/submissions', ['records' => []])->assertStatus(422);
});

test('an account that was never locked is not affected', function () {
    $this->post('/login', ['identifier' => '0244000811', 'password' => 'Password@123'])->assertRedirect();

    $this->assertAuthenticated();
});
