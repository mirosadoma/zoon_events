<?php

namespace App\Modules\Events\Domain\Context;

final readonly class PublicEventContext
{
    public function __construct(
        public string $tenantId,
        public string $eventId,
        public string $host,
        public string $eventSlug,
        public ?string $eventEndsAt = null,
    ) {}
}
