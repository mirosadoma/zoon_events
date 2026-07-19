<?php

namespace App\Modules\Attendees\Domain;

enum AttendeeInviteStatus: string
{
    case NotRegistered = 'not_registered';
    case Registered = 'registered';
    case Attended = 'attended';
    case NotAttended = 'not_attended';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
