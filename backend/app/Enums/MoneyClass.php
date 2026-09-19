<?php

namespace App\Enums;

enum MoneyClass: string
{
    case Asset = 'asset';
    case Expenditure = 'expenditure';
    case Income = 'income';
    case Liability = 'liability';
}
