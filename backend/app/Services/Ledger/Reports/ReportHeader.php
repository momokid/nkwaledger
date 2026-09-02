<?php

namespace App\Services\Ledger\Reports;

use App\Models\FarmerProfile;
use Illuminate\Support\Carbon;
use RuntimeException;

class ReportHeader
{
    public const NOTICE = '';

    public function __construct(
        public readonly string $title,
        public readonly string $farmerName,
        public readonly ?string $farmerPhone,
        // the uuid, so nobody can find a farmer by counting upward
        public readonly string $farmerReference,
        public readonly string $from,
        public readonly string $to,
        public readonly bool $includeProvisional,
        public readonly string $preparedBy,
        public readonly Carbon $generatedAt,
        public readonly string $verificationCode,
        public readonly string $notice,
    ) {}

    public static function make(
        string $title,
        FarmerProfile $farmerProfile,
        string $from,
        string $to,
        bool $includeProvisional,
        array $figures,
    ): self {
        $user = $farmerProfile->user;

        return new self(
            title: $title,
            farmerName: trim("{$user?->surname} {$user?->first_name}"),
            farmerPhone: $user?->phone,
            farmerReference: $farmerProfile->uuid,
            from: $from,
            to: $to,
            includeProvisional: $includeProvisional,
            // always the system, so the same report always signs the same way
            preparedBy: 'NkwaLedger',
            generatedAt: now(),
            verificationCode: self::sign($title, $farmerProfile, $from, $to, $includeProvisional, $figures),
            notice: self::NOTICE,
        );
    }

    // built from what the report says, never from when it was printed
    private static function sign(
        string $title,
        FarmerProfile $farmerProfile,
        string $from,
        string $to,
        bool $includeProvisional,
        array $figures,
    ): string {
        $secret = config('app.report_secret');

        if (blank($secret)) {
            throw new RuntimeException('No report secret is set, so reports cannot be signed.');
        }

        ksort($figures);

        $payload = implode('|', [
            $title,
            $farmerProfile->uuid,
            $from,
            $to,
            $includeProvisional ? 'all' : 'confirmed',
            json_encode($figures),
        ]);

        $hash = hash_hmac('sha256', $payload, $secret);

        return strtoupper(substr(preg_replace('/[^a-z0-9]/i', '', $hash), 0, 12));
    }
}
