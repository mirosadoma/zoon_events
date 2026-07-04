<?php

namespace App\Modules\Events\Domain\Events;

final readonly class EventPublished
{
    public function __construct(
        public string $tenantId,
        public string $eventId,
        public string $actorId,
    ) {}
}
