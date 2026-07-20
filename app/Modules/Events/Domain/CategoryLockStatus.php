<?php

namespace App\Modules\Events\Domain;

enum CategoryLockStatus: string
{
    case Published = 'published';
    case RegistrationOpen = 'registration_open';
    case RegistrationClosed = 'registration_closed';
    case Live = 'live';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function locksCategories(string $status): bool
    {
        return in_array($status, self::values(), true);
    }
}
