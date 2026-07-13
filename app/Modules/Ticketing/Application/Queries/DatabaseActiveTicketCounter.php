<?php

namespace App\Modules\Ticketing\Application\Queries;

use App\Modules\Events\Domain\EventRegistrationProfile;
use App\Modules\Ticketing\Contracts\ActiveTicketCounter;
use App\Modules\Ticketing\Infrastructure\Persistence\Models\TicketType;

final class DatabaseActiveTicketCounter implements ActiveTicketCounter
{
    public function countForEvent(string $tenantId, string $eventId): int
    {
        return TicketType::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('status', 'active')
            ->count();
    }

    public function countOrganizerTicketTypesForEvent(string $tenantId, string $eventId): int
    {
        return TicketType::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('status', 'active')
            ->where('code', '!=', EventRegistrationProfile::SYSTEM_REGISTRATION_TICKET_CODE)
            ->count();
    }
}
