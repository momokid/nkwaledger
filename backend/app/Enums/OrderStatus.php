<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Requested = 'requested';
    case Confirmed = 'confirmed';
    case Received = 'received';
    case Closed = 'closed';
}
