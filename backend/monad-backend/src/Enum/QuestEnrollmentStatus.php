<?php

namespace App\Enum;

enum QuestEnrollmentStatus: string
{
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case ABANDONED = 'abandoned';
}
