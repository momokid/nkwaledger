<?php

namespace Database\Seeders;

use App\Models\MarketplaceSetting;
use App\Services\SettingsService;
use Illuminate\Database\Seeder;

class MarketplaceSettingSeeder extends Seeder
{
    public function run(): void
    {
        foreach (SettingsService::DEFAULTS as $key => $value) {
            MarketplaceSetting::firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
