<?php

namespace App\Modules\Ticketing\Application\Queries;

use App\Modules\Events\Domain\EventRegistrationProfile;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Ticketing\Infrastructure\Persistence\Models\TicketType;
use Illuminate\Support\Collection;

final class PublicTicketTypeCatalog
{
    /** @return Collection<int, TicketType> */
    public function forEvent(Event $event): Collection
    {
        $tickets = TicketType::query()
            ->where('tenant_id', $event->tenant_id)
            ->where('event_id', $event->id)
            ->where('status', 'active')
            ->orderBy('created_at')
            ->get();

        if (EventRegistrationProfile::requiresTicketConfiguration($event)) {
            return $tickets->reject(
                fn (TicketType $ticket): bool => $ticket->code === EventRegistrationProfile::SYSTEM_REGISTRATION_TICKET_CODE,
            )->values();
        }

        return $tickets->filter(
            fn (TicketType $ticket): bool => $ticket->code === EventRegistrationProfile::SYSTEM_REGISTRATION_TICKET_CODE,
        )->values();
    }
}
