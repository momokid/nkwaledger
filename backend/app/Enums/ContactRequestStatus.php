<?php

namespace App\Enums;

enum ContactRequestStatus: string
{
    case Sent = 'sent';
    case Replied = 'replied';
    case Expired = 'expired';
}
