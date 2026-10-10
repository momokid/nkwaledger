<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\DB;

// one record, one door: the web form and the sync endpoint both run their check-then-post in here
class RecordLock
{
    // PostgreSQL only. The lock is held until the surrounding transaction commits, so whoever
    // comes second always sees the first one's committed record. Other drivers run as before.
    public function around(int $userId, ?string $uuid, Closure $work): mixed
    {
        if ($uuid === null || DB::getDriverName() !== 'pgsql') {
            return $work();
        }

        return DB::transaction(function () use ($userId, $uuid, $work) {
            DB::select('select pg_advisory_xact_lock(hashtextextended(?, 0))', ["{$userId}:{$uuid}"]);

            return $work();
        });
    }
}
