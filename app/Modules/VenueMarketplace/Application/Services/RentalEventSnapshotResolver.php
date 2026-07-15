<?php

namespace App\Modules\VenueMarketplace\Application\Services;

use App\Modules\Events\Application\Contracts\MarketplaceEventReader;
use App\Modules\VenueMarketplace\Domain\Exceptions\MarketplaceDomainException;
use App\Modules\VenueMarketplace\Http\Problems\Phase6Problem;

final readonly class RentalEventSnapshotResolver
{
    public function __construct(private MarketplaceEventReader $events) {}

    /** @return array{event_id:int,snapshot:array<string,mixed>} */
    public function resolve(int $organizerTenantId, int $eventId): array
    {
        $result = $this->events->readOwnedEvent($organizerTenantId, $eventId);
        if (! $result->foundEvent() || $result->event === null) {
            throw new MarketplaceDomainException(match ($result->reason) {
                'marketplace_event_ineligible_status' => Phase6Problem::MARKETPLACE_EVENT_INELIGIBLE_STATUS,
                'marketplace_event_window_invalid' => Phase6Problem::MARKETPLACE_WINDOW_INVALID,
                default => Phase6Problem::MARKETPLACE_EVENT_NOT_FOUND,
            });
        }
        $event = $result->event;
        if ($event->tenantId !== $organizerTenantId) {
            throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_EVENT_NOT_FOUND);
        }

        return [
            'event_id' => $event->eventId,
            'snapshot' => [
                'id' => $event->eventPublicId !== '' ? $event->eventPublicId : (string) $event->eventId,
                'name' => ['en' => $event->nameEn, 'ar' => $event->nameAr],
                'status' => $event->status,
                'timezone' => $event->window->timezone,
                'start_at' => $event->window->startsAt->format(DATE_ATOM),
                'end_at' => $event->window->endsAt->format(DATE_ATOM),
            ],
        ];
    }
}
