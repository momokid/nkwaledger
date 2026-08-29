<?php

namespace App\Enums;

enum IdentityType: string
{
    case GhanaCard = 'ghana_card';
    case Passport = 'passport';
    case VoterId = 'voter_id';

    public function label(): string
    {
        return match ($this) {
            self::GhanaCard => 'Ghana Card',
            self::Passport => 'Passport',
            self::VoterId => 'Voter ID',
        };
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            fn(self $type) => ['value' => $type->value, 'label' => $type->label()],
            self::cases(),
        );
    }
}
