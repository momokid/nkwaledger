<?php

namespace App\Services;

use App\Models\MarketplaceSetting;
use App\Models\User;
use InvalidArgumentException;

class SettingsService
{
    // the single source of truth for every marketplace default; the seeder reads this too
    public const DEFAULTS = [
        'marketplace.supplier_report_response_days' => '3',
        'marketplace.admin_intervention_days' => '15',
        'marketplace.buyer_confirmation_days' => '15',
        'marketplace.crop_listing_reminder_days' => '5',
        'marketplace.animal_listing_prompt_days' => '15',
        'marketplace.product_expiry_alert_days' => '30',
        'marketplace.price_stale_days' => '15',
        'marketplace.kiosks_per_email_cap' => '1',
        'marketplace.central_contact_number' => null,
    ];

    public function __construct(private readonly AuditService $audit) {}

    public function get(string $key): ?string
    {
        $this->guardKnownKey($key);

        $stored = MarketplaceSetting::where('key', $key)->value('value');

        return $stored ?? self::DEFAULTS[$key];
    }

    public function getInt(string $key): int
    {
        $value = $this->get($key);

        if ($value === null) {
            throw new InvalidArgumentException("Marketplace setting \"{$key}\" has no value to read as an integer.");
        }

        return (int) $value;
    }

    public function set(string $key, string $value, User $updatedBy): MarketplaceSetting
    {
        $this->guardKnownKey($key);

        $before = MarketplaceSetting::where('key', $key)->value('value');

        $setting = MarketplaceSetting::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'updated_by' => $updatedBy->id],
        );

        $this->audit->record('marketplace_setting.updated', [
            'key' => $key,
            'old_value' => $before,
            'new_value' => $value,
        ]);

        return $setting;
    }

    private function guardKnownKey(string $key): void
    {
        if (! array_key_exists($key, self::DEFAULTS)) {
            throw new InvalidArgumentException("Unknown marketplace setting \"{$key}\".");
        }
    }
}
