<?php

namespace App\Modules\Events\Domain;

enum EventTier: string
{
    case Public = 'public';
    case Private = 'private';
    case Both = 'both';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
