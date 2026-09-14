<?php

namespace App\Enums;

enum DiseaseReportStatus: string
{
    case New = 'new';
    case Reviewed = 'reviewed';
    case Resolved = 'resolved';
}
