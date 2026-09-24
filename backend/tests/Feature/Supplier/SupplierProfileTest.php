<?php

use App\Models\OtpCode;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

function activatedSupplierUser(): User
{
    $user = User::factory()->create(['password' => bcrypt('Password@123')]);
    $user->assignRole('supplier');

    return $user;
}

test('a guest cannot reach the supplier profile form', function () {
    $this->get('/supplier/profile/create')->assertRedirect('/login');
});

test('a farmer cannot reach the supplier profile form', function () {
    $farmer = User::factory()->create();
    $farmer->assignRole('farmer');

    $this->actingAs($farmer)->get('/supplier/profile/create')->assertForbidden();
});

test('a supplier can reach their own profile form', function () {
    $this->actingAs(activatedSupplierUser())->get('/supplier/profile/create')->assertOk();
});

test('creating a supplier profile sends an email verification code and does not verify it yet', function () {
    Mail::fake();
    $user = activatedSupplierUser();

    $this->actingAs($user)->post('/supplier/profile', [
        'business_name' => 'Adom Agro Inputs',
        'email' => 'adom@example.com',
    ])->assertSessionHasNoErrors()->assertRedirect();

    $this->assertDatabaseHas('suppliers', [
        'user_id' => $user->id,
        'email' => 'adom@example.com',
        'email_verified_at' => null,
    ]);

    Mail::assertSent(\App\Mail\OtpMail::class);
});

test('a duplicate email is rejected even across different suppliers', function () {
    Supplier::factory()->create(['email' => 'taken@example.com']);

    $this->actingAs(activatedSupplierUser())->post('/supplier/profile', [
        'email' => 'taken@example.com',
    ])->assertSessionHasErrors('email');
});

test('a suspended supplier email cannot be reused to register again', function () {
    Supplier::factory()->suspended()->create(['email' => 'blocked@example.com']);

    $this->actingAs(activatedSupplierUser())->post('/supplier/profile', [
        'email' => 'blocked@example.com',
    ])->assertSessionHasErrors('email');
});

test('the right code verifies the supplier email', function () {
    $user = activatedSupplierUser();
    $supplier = Supplier::factory()->create(['user_id' => $user->id, 'email' => 'adom@example.com']);

    OtpCode::create([
        'identifier' => 'adom@example.com',
        'code' => Hash::make('654321'),
        'type' => 'supplier_email_verification',
        'expires_at' => now()->addMinutes(5),
    ]);

    $this->actingAs($user)->post('/supplier/profile/verify-email', ['code' => '654321'])
        ->assertSessionHasNoErrors();

    expect($supplier->fresh()->email_verified_at)->not->toBeNull();
});

test('the wrong code does not verify the supplier email', function () {
    $user = activatedSupplierUser();
    $supplier = Supplier::factory()->create(['user_id' => $user->id, 'email' => 'adom@example.com']);

    OtpCode::create([
        'identifier' => 'adom@example.com',
        'code' => Hash::make('654321'),
        'type' => 'supplier_email_verification',
        'expires_at' => now()->addMinutes(5),
    ]);

    $this->actingAs($user)->post('/supplier/profile/verify-email', ['code' => '000000'])
        ->assertSessionHasErrors('code');

    expect($supplier->fresh()->email_verified_at)->toBeNull();
});
