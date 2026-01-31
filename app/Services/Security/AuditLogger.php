<?php

namespace App\Services\Security;

use Illuminate\Support\Facades\DB;

class AuditLogger
{
    public function log(
        string $eventType,
        ?string $phoneNumber = null,
        ?int $userId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        array $meta = []
    ): void {
        DB::table('security_audit_logs')->insert([
            'event_type' => $eventType,
            'phone_number' => $phoneNumber,
            'user_id' => $userId,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'meta' => json_encode($meta),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
