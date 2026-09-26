<?php

namespace App\Enums;

enum CommissionStatus: string
{
    case PendingAdmin = 'pending_admin';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
