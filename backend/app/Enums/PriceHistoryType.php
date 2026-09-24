<?php

namespace App\Enums;

enum PriceHistoryType: string
{
    case Changed = 'changed';
    case Confirmed = 'confirmed';
}
