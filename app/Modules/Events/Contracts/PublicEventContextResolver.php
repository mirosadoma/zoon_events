<?php

namespace App\Modules\Events\Contracts;

use App\Modules\Events\Domain\Context\PublicEventContext;

interface PublicEventContextResolver
{
    public function resolve(string $host, string $eventSlug): ?PublicEventContext;
}
