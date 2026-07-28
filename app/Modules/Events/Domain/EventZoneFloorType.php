<?php

namespace App\Modules\Events\Domain;

enum EventZoneFloorType: string
{
    case Basement = 'basement';
    case Floor = 'floor';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $type): string => $type->value, self::cases());
    }
}
