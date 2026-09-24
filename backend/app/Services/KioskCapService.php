<?php

namespace App\Services;

use App\Models\Supplier;

class KioskCapService
{
    public function __construct(private readonly SettingsService $settings) {}

    public function exceedsCap(Supplier $supplier): bool
    {
        return $this->kioskCountForIdentity($supplier) >= $this->settings->getInt('marketplace.kiosks_per_email_cap');
    }

    // an unproven email or phone cannot stand in for a proven identity, so an unverified
    // supplier's own kiosks never count - a user's phone and a supplier's email are already
    // unique app-wide, so once both are verified this is simply the supplier's own count
    private function kioskCountForIdentity(Supplier $supplier): int
    {
        if (! $supplier->isVerifiedIdentity()) {
            return 0;
        }

        return $supplier->kiosks()->count();
    }
}
