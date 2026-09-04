<?php

namespace App\Enums;

enum MovementReason: string
{
    case Opening = 'opening';
    case Birth = 'birth';
    case Purchase = 'purchase';
    case Death = 'death';
    case Theft = 'theft';
    case Sale = 'sale';
    case Cull = 'cull';
    case Correction = 'correction';
    case Loss = 'loss';

    // the farmer picks a reason and the direction follows, so it cannot be set the wrong way
    public function addsToCount(): bool
    {
        return match ($this) {
            self::Opening, self::Birth, self::Purchase => true,
            default => false,
        };
    }

    // a miscount can go either way, so this one is told which
    public function needsDirection(): bool
    {
        return $this === self::Correction;
    }

    public function label(): string
    {
        return match ($this) {
            self::Opening => 'Starting count',
            self::Birth => 'New birth',
            self::Purchase => 'Bought more',
            self::Death => 'Died',
            self::Theft => 'Stolen',
            self::Sale => 'Sold',
            self::Cull => 'Removed on purpose',
            self::Correction => 'Fixing a miscount',
            self::Loss => 'Lost',
        };
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            fn(self $reason) => ['value' => $reason->value, 'label' => $reason->label()],
            // the starting count is written by the system, never chosen by a person
            array_values(array_filter(self::cases(), fn(self $reason) => $reason !== self::Opening)),
        );
    }
}
