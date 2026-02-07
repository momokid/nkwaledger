<?php

namespace App\Services\Ledger;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PeriodLockService
{
    public function isDateLocked(string $date): bool
    {
        return DB::table('ledger_periods')
            ->where('is_locked', true)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->exists();
    }

    public function lockPeriod(
        string $startDate,
        string $endDate,
        int $userId
    ): void {

        DB::transaction(function () use ($startDate, $endDate, $userId) {

            DB::table('ledger_periods')
                ->updateOrInsert(
                    [
                        'start_date' => $startDate,
                        'end_date'   => $endDate,
                    ],
                    [
                        'is_locked' => true,
                        'locked_by' => $userId,
                        'locked_at' => now(),
                        'updated_at'=> now(),
                        'created_at'=> now(),
                    ]
                );
        });
    }
}
