<?php

namespace App\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;

// the age and clock rules for a synced ledger record, judged only against the server's own time
class SyncRecordRules
{
    public const TOO_OLD = 'too_old';
    public const CLOCK_AHEAD = 'clock_ahead';

    private const TEXT = [
        self::TOO_OLD => 'This record is too old to be accepted.',
        self::CLOCK_AHEAD => 'The date on the phone was ahead of the real date.',
    ];

    // the code of the rule the record breaks, or null; a phone far ahead is checked first, since its other times mean little
    public function breachOf(array $record, CarbonInterface $received): ?string
    {
        $phoneAhead = Carbon::parse($record['device_created_at'])->getTimestamp() - $received->getTimestamp();

        if ($phoneAhead > config('sync_rules.max_clock_ahead_minutes') * 60) {
            return self::CLOCK_AHEAD;
        }

        $maxDays = config('sync_rules.max_age_days');
        $oldestDay = $received->copy()->startOfDay()->subDays($maxDays);
        $recordDay = Carbon::parse($record['event_date'], $received->getTimezone())->startOfDay();

        return $recordDay->lt($oldestDay) || -$phoneAhead > $maxDays * 86400 ? self::TOO_OLD : null;
    }

    public function text(string $code): string
    {
        return self::TEXT[$code];
    }
}
