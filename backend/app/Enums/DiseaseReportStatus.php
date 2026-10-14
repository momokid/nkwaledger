<?php

namespace App\Enums;

enum DiseaseReportStatus: string
{
    // saved offline, text only: invisible to everyone until its photo arrives
    case WaitingForPhoto = 'waiting_for_photo';
    case New = 'new';
    case Reviewed = 'reviewed';
    case Resolved = 'resolved';
}
