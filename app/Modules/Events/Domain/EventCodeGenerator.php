<?php

namespace App\Modules\Events\Domain;

use App\Modules\Events\Infrastructure\Persistence\Models\Event;

final class EventCodeGenerator
{
    public function generate(): string
    {
        do {
            $code = str_pad((string) random_int(0, 99_999_999), 8, '0', STR_PAD_LEFT);
        } while (Event::query()->withoutGlobalScopes()->where('code', $code)->exists());

        return $code;
    }
}
