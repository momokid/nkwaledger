<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

uses(RefreshDatabase::class);

afterEach(function () {
    Mockery::close();
    app()->forgetInstance(\Laravel\Socialite\Contracts\Factory::class);
});

function mockSocialiteUser(string $provider, array $overrides = []): void
{
    $socialiteUser = Mockery::mock(SocialiteUser::class);
    $socialiteUser->shouldReceive('getId')->andReturn($overrides['id'] ?? '123456789');
    $socialiteUser->shouldReceive('getName')->andReturn($overrides['name'] ?? 'Kwame Mensah');
    $socialiteUser->shouldReceive('getEmail')->andReturn($overrides['email'] ?? 'kwame@example.com');
    $socialiteUser->shouldReceive('getAvatar')->andReturn($overrides['avatar'] ?? null);
    $socialiteUser->token = $overrides['token'] ?? 'fake-token';

    Socialite::shouldReceive('driver')->with($provider)->andReturnSelf();
    Socialite::shouldReceive('stateless')->andReturnSelf();
    Socialite::shouldReceive('user')->andReturn($socialiteUser);
}

test('google redirect sends user to google', function () {
    Socialite::shouldReceive('driver')->with('google')->andReturnSelf();
    Socialite::shouldReceive('stateless')->andReturnSelf();
    Socialite::shouldReceive('redirect')
        ->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));

    $response = $this->get('/auth/google');

    $response->assertRedirect();
});

test('facebook redirect sends user to facebook', function () {
    Socialite::shouldReceive('driver')->with('facebook')->andReturnSelf();
    Socialite::shouldReceive('stateless')->andReturnSelf();
    Socialite::shouldReceive('redirect')
        ->andReturn(redirect('https://www.facebook.com/v3.3/dialog/oauth'));

    $response = $this->get('/auth/facebook');

    $response->assertRedirect();
});

test('new google oauth user is redirected to complete profile page', function () {
    mockSocialiteUser('google');

    $response = $this->get('/auth/google/callback');

    $response->assertRedirect('/complete-profile');
});

test('new facebook oauth user is redirected to complete profile page', function () {
    mockSocialiteUser('facebook');

    $response = $this->get('/auth/facebook/callback');

    $response->assertRedirect('/complete-profile');
});

test('new oauth user details are stored in session', function () {
    mockSocialiteUser('google');

    $this->get('/auth/google/callback');

    expect(session('oauth.provider'))->toBe('google');
    expect(session('oauth.email'))->toBe('kwame@example.com');
    expect(session('oauth.name'))->toBe('Kwame Mensah');
});

test('new oauth user is not created in database until profile is completed', function () {
    mockSocialiteUser('google');

    $this->get('/auth/google/callback');

    expect(User::where('email', 'kwame@example.com')->exists())->toBeFalse();
});

test('existing user is found and not duplicated on oauth login', function () {
    User::factory()->withEmail()->create([
        'email' => 'kwame@example.com',
    ]);

    mockSocialiteUser('google', ['email' => 'kwame@example.com']);

    $this->get('/auth/google/callback');

    expect(User::where('email', 'kwame@example.com')->count())->toBe(1);
});

test('existing oauth user with phone is redirected to dashboard', function () {
    User::factory()->withEmail()->create([
        'email' => 'kwame@example.com',
        'phone' => '+233244000001',
    ]);

    mockSocialiteUser('google', ['email' => 'kwame@example.com']);

    $response = $this->get('/auth/google/callback');

    $response->assertRedirect('/farmer/dashboard');
});
