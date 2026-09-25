<?php

namespace App\Enums;

enum KioskReportStatus: string
{
    case Open = 'open';
    case SupplierAnswered = 'supplier_answered';
    case WithAdmin = 'with_admin';
    case Resolved = 'resolved';
    case Suspended = 'suspended';
}
