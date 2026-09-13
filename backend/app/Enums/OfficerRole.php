<?php

namespace App\Enums;

enum OfficerRole: string
{
    case Vet = 'vet';
    case Adviser = 'adviser';

    // the farm unit's own category already says what kind of problem this is,
    // so no classification step is needed to know who should see it
    public static function fromFarmCategory(string $category): self
    {
        return match ($category) {
            'Crop' => self::Adviser,
            'Livestock', 'Aquatic' => self::Vet,
            default => throw new \InvalidArgumentException("No officer role is routed for the farm category [{$category}]."),
        };
    }
}
