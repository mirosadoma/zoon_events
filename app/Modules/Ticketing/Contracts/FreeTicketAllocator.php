<?php

namespace App\Modules\Ticketing\Contracts;

use App\Modules\Ticketing\Domain\FreeTicketAllocation;

interface FreeTicketAllocator
{
    public function reserve(string $tenantId, string $eventId, string $ticketTypeId): FreeTicketAllocation;

    public function linkAndConvert(string $tenantId, string $holdId, string $orderId): void;
}
