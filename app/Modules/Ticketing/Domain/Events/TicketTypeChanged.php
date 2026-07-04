<?php

namespace App\Modules\Ticketing\Domain\Events;

final readonly class TicketTypeChanged
{
    public function __construct(
        public string $tenantId,
        public string $eventId,
        public string $ticketTypeId,
        public string $action,
        public string $actorId,
    ) {}
}
