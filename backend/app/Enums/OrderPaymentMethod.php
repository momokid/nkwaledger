<?php

namespace App\Enums;

enum OrderPaymentMethod: string
{
    case Bank = 'bank';
    case CashOnDelivery = 'cod';
}
