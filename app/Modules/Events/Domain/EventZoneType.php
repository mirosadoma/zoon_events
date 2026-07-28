<?php

namespace App\Modules\Events\Domain;

enum EventZoneType: string
{
    case Gate = 'gate';
    case Hall = 'hall';
    case Stage = 'stage';
    case Room = 'room';
    case Vip = 'vip';
    case Parking = 'parking';
    case Outdoor = 'outdoor';
    case Other = 'other';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $type): string => $type->value, self::cases());
    }
}
