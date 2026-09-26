<?php

namespace App\Enums;

enum ProduceListingStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Sold = 'sold';
    case Withdrawn = 'withdrawn';
    case Expired = 'expired';
    case Hidden = 'hidden';
}
