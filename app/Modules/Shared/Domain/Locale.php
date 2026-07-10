<?php

namespace App\Modules\Shared\Domain;

enum Locale: string
{
    case English = 'en';
    case Arabic = 'ar';

    public static function default(): self
    {
        return self::English;
    }
}
