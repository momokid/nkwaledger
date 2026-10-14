<?php

namespace App\Enums;

enum OrderPaymentMethod: string
{
    case Bank = 'bank';
    case Momo = 'momo';
    case CashOnDelivery = 'cod';
}
