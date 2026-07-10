<?php

namespace App\Modules\Ticketing\Contracts;

use App\Modules\Ticketing\Domain\PaidTicketAllocation;

interface PaidTicketAllocator
{
    public function reserve(string $tenantId, string $eventId, string $ticketTypeId): PaidTicketAllocation;

    public function linkAndConvert(string $tenantId, string $holdId, string $orderId): void;
}
