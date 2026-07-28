<?php

namespace App\Modules\Events\Domain;

enum EventCoordinateSpace: string
{
    case Relative = 'relative';
    case Geo = 'geo';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(
            static fn (self $case): string => $case->value,
            self::cases(),
        );
    }
}
