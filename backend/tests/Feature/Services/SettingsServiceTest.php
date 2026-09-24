<?php

use App\Models\MarketplaceSetting;
use App\Models\User;
use App\Services\SettingsService;

beforeEach(function () {
    $this->settings = app(SettingsService::class);
});

test('a key with no stored row falls back to its known default', function () {
    expect($this->settings->getInt('marketplace.kiosks_per_email_cap'))->toBe(1)
        ->and($this->settings->getInt('marketplace.buyer_confirmation_days'))->toBe(15);
});

test('setting a value stores it and later reads return the stored value, not the default', function () {
    $admin = User::factory()->create();

    $this->settings->set('marketplace.kiosks_per_email_cap', '3', $admin);

    expect($this->settings->getInt('marketplace.kiosks_per_email_cap'))->toBe(3);
});

test('setting a value records who changed it', function () {
    $admin = User::factory()->create();

    $this->settings->set('marketplace.price_stale_days', '20', $admin);

    $this->assertDatabaseHas('marketplace_settings', [
        'key' => 'marketplace.price_stale_days',
        'value' => '20',
        'updated_by' => $admin->id,
    ]);
});

test('setting a value writes an audit log entry', function () {
    $admin = User::factory()->create();

    $this->actingAs($admin)->settings->set('marketplace.price_stale_days', '20', $admin);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'marketplace_setting.updated',
        'user_id' => $admin->id,
    ]);
});

test('changing a setting again updates the same row rather than creating a second one', function () {
    $admin = User::factory()->create();

    $this->settings->set('marketplace.price_stale_days', '20', $admin);
    $this->settings->set('marketplace.price_stale_days', '25', $admin);

    expect(MarketplaceSetting::where('key', 'marketplace.price_stale_days')->count())->toBe(1)
        ->and($this->settings->getInt('marketplace.price_stale_days'))->toBe(25);
});

test('an unknown key with no default throws rather than silently returning null', function () {
    expect(fn() => $this->settings->getInt('marketplace.not_a_real_key'))
        ->toThrow(InvalidArgumentException::class);
});
