<?php

namespace App\Enums;

enum OrderEventType: string
{
    case Requested = 'requested';
    case Confirmed = 'confirmed';
    case Received = 'received';
    case Closed = 'closed';
}
