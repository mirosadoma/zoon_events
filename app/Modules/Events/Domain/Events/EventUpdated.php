<?php

namespace App\Modules\Events\Domain\Events;

final readonly class EventUpdated
{
    public function __construct(
        public string $tenantId,
        public string $eventId,
    ) {}
}
