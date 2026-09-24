<?php

namespace App\Enums;

enum KioskStatus: string
{
    case PendingConfirmation = 'pending_confirmation';
    case Active = 'active';
    case Suspended = 'suspended';
}
