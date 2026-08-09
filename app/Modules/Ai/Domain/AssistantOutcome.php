<?php

namespace App\Modules\Ai\Domain;

enum AssistantOutcome: string
{
    case Answered = 'answered';
    case Unanswered = 'unanswered';
    case Refused = 'refused';
    case Throttled = 'throttled';
    case Unavailable = 'unavailable';
}
