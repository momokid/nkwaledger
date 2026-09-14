<?php

namespace App\Enums;

enum ContactMethod: string
{
    case Call = 'call';
    case FarmVisit = 'farm_visit';
    case OfficeVisit = 'office_visit';
}
