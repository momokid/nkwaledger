<?php

namespace App\Enums;

enum StockSource: string
{
    // what the farmer already had when they joined, posts against Stated Capital
    case OpeningBalance = 'opening_balance';

        // bought with money, posts against cash
    case Purchase = 'purchase';

    public function label(): string
    {
        return match ($this) {
            self::OpeningBalance => 'Already had it',
            self::Purchase => 'Bought it',
        };
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            fn(self $source) => ['value' => $source->value, 'label' => $source->label()],
            self::cases(),
        );
    }
}
