<?php

namespace App\Modules\Events\Domain;

enum EventType: string
{
    case Seminar = 'seminar';
    case Conference = 'conference';
    case Workshop = 'workshop';
    case CorporateGathering = 'corporate_gathering';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $type): string => $type->value, self::cases());
    }
}
