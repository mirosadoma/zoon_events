<?php

namespace App\Modules\Ticketing\Contracts;

interface ActiveTicketCounter
{
    public function countForEvent(string $tenantId, string $eventId): int;

    /** Count active ticket types configured by the organizer (excludes system registration slots). */
    public function countOrganizerTicketTypesForEvent(string $tenantId, string $eventId): int;
}
