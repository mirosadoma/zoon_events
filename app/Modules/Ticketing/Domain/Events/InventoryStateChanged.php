<?php

namespace App\Modules\Ticketing\Domain\Events;

final readonly class InventoryStateChanged
{
    public function __construct(
        public string $tenantId,
        public string $eventId,
        public string $ticketTypeId,
        public string $holdId,
        public string $transition,
    ) {}
}
