<?php

namespace App\Modules\Events\Application\Context;

use App\Modules\Events\Contracts\PublicEventContextResolver;
use App\Modules\Events\Domain\Context\PublicEventContext;

final class NullPublicEventContextResolver implements PublicEventContextResolver
{
    public function resolve(string $host, string $eventSlug): ?PublicEventContext
    {
        return null;
    }
}
