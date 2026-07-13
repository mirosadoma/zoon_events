<?php

namespace App\Modules\Events\Domain;

enum RegistrationMode: string
{
    case FreeRegistration = 'free_registration';
    case PaidTicketing = 'paid_ticketing';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $mode): string => $mode->value, self::cases());
    }
}
