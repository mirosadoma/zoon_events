<?php

namespace App\Modules\Events\Domain;

enum EventZoneType: string
{
    case Hall = 'hall';
    case Stage = 'stage';
    case Room = 'room';
    case Vip = 'vip';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $type): string => $type->value, self::cases());
    }
}
