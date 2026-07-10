<?php

namespace App\Modules\Ticketing\Contracts;

interface ActiveTicketCounter
{
    public function countForEvent(string $tenantId, string $eventId): int;
}
