<?php

namespace App\Modules\Shared\Http\Resources;

use App\Modules\Shared\Domain\Context\RequestContextStore;

final class ApiMeta
{
    public static function base(RequestContextStore $store): array
    {
        return [
            'correlation_id' => $store->current()?->correlationId->value,
        ];
    }
}
