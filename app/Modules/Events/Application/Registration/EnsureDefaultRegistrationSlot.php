<?php

namespace App\Modules\Events\Application\Registration;

use App\Modules\Events\Domain\EventRegistrationProfile;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Ticketing\Infrastructure\Persistence\Models\TicketInventory;
use App\Modules\Ticketing\Infrastructure\Persistence\Models\TicketType;

final class EnsureDefaultRegistrationSlot
{
    public function execute(Event $event, ?int $actorUserId = null): ?TicketType
    {
        if (EventRegistrationProfile::requiresTicketConfiguration($event)) {
            return null;
        }

        $ticket = TicketType::query()->updateOrCreate(
            [
                'tenant_id' => $event->tenant_id,
                'event_id' => $event->id,
                'code' => EventRegistrationProfile::SYSTEM_REGISTRATION_TICKET_CODE,
            ],
            [
                'name_en' => 'Registration',
                'name_ar' => 'تسجيل',
                'attendee_type' => 'general',
                'base_price_minor' => 0,
                'currency' => 'EGP',
                'sale_starts_at' => $event->registration_opens_at,
                'sale_ends_at' => $event->registration_closes_at,
                'status' => 'active',
                'created_by_user_id' => $actorUserId ?? $event->created_by_user_id,
            ],
        );

        TicketInventory::query()->updateOrCreate(
            [
                'tenant_id' => $event->tenant_id,
                'event_id' => $event->id,
                'ticket_type_id' => $ticket->id,
            ],
            [
                'capacity' => max(1, (int) ($event->capacity ?? 1)),
                'held_quantity' => 0,
                'sold_quantity' => 0,
            ],
        );

        return $ticket;
    }
}
