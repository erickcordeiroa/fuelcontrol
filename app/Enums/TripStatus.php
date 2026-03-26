<?php

namespace App\Enums;

enum TripStatus: string
{
    case Completed = 'completed';
    case InProgress = 'in_progress';
}
