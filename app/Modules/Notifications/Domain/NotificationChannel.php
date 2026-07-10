<?php

namespace App\Modules\Notifications\Domain;

enum NotificationChannel: string
{
    case Email = 'email';
    case Sms = 'sms';
}
