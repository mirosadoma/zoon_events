<?php

namespace App\Modules\Notifications\Domain;

enum NotificationStatus: string
{
    case Accepted = 'accepted';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case TemporaryFailure = 'temporary_failure';
    case PermanentFailure = 'permanent_failure';
    case Unknown = 'unknown';
}
