<?php

namespace App\Modules\Events\Application\Registration;

use App\Modules\Events\Domain\EventRegistrationProfile;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Events\Infrastructure\Persistence\Models\EventCategoryVenueDay;
use App\Modules\Ticketing\Infrastructure\Persistence\Models\TicketInventory;
use App\Modules\Ticketing\Infrastructure\Persistence\Models\TicketType;

final class EnsureDefaultRegistrationSlot
{
    public function execute(Event $event, ?int $actorUserId = null): ?TicketType
    {
        if (EventRegistrationProfile::requiresTicketConfiguration($event)) {
            return null;
        }

        $saleStartsAt = $event->registration_opens_at;
        $saleEndsAt = $event->registration_closes_at;

        if ($saleStartsAt === null || $saleEndsAt === null) {
            $venue = $event->venues()->orderBy('sort_order')->first();
            $saleStartsAt = $saleStartsAt ?? $venue?->registration_opens_at;
            $saleEndsAt = $saleEndsAt ?? $venue?->registration_closes_at;
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
                'sale_starts_at' => $saleStartsAt,
                'sale_ends_at' => $saleEndsAt,
                'status' => 'active',
                'created_by_user_id' => $actorUserId ?? $event->created_by_user_id,
            ],
        );

        $inventoryCapacity = (int) ($event->capacity ?? 0);
        if ($inventoryCapacity < 1) {
            $inventoryCapacity = (int) EventCategoryVenueDay::query()
                ->whereHas(
                    'categoryVenue.category',
                    fn ($query) => $query->where('event_id', $event->id),
                )
                ->sum('capacity');
        }

        TicketInventory::query()->updateOrCreate(
            [
                'tenant_id' => $event->tenant_id,
                'event_id' => $event->id,
                'ticket_type_id' => $ticket->id,
            ],
            [
                'capacity' => max(1, $inventoryCapacity),
                'held_quantity' => 0,
                'sold_quantity' => 0,
            ],
        );

        return $ticket;
    }
}
