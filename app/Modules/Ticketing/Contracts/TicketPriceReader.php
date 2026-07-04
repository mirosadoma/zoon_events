<?php

namespace App\Modules\Ticketing\Contracts;

use App\Modules\Ticketing\Domain\ValueObjects\Money;

interface TicketPriceReader
{
    public function price(string $tenantId, string $eventId, string $ticketTypeId): Money;
}
