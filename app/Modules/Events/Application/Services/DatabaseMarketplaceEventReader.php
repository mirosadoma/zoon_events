<?php

namespace App\Modules\Events\Application\Services;

use App\Modules\Events\Application\Contracts\MarketplaceEventReader;
use App\Modules\Events\Application\Contracts\MarketplaceEventReadResult;
use App\Modules\Events\Application\Contracts\MarketplaceEventSnapshot;
use App\Modules\Events\Application\Contracts\MarketplaceEventWindow;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use DateTimeImmutable;

final class DatabaseMarketplaceEventReader implements MarketplaceEventReader
{
    private const ELIGIBLE_STATUSES = ['published', 'registration_open', 'registration_closed', 'live'];

    public function readOwnedEvent(int $organizerTenantId, int $eventId): MarketplaceEventReadResult
    {
        $event = Event::query()
            ->where('tenant_id', $organizerTenantId)
            ->whereKey($eventId)
            ->first();

        if ($event === null) {
            return MarketplaceEventReadResult::denied('marketplace_event_not_found');
        }

        if (! in_array($event->status, self::ELIGIBLE_STATUSES, true)) {
            return MarketplaceEventReadResult::denied('marketplace_event_ineligible_status');
        }

        if ($event->start_at === null || $event->end_at === null || $event->end_at <= $event->start_at) {
            return MarketplaceEventReadResult::denied('marketplace_event_window_invalid');
        }

        return MarketplaceEventReadResult::found(new MarketplaceEventSnapshot(
            tenantId: (int) $event->tenant_id,
            eventId: (int) $event->id,
            eventPublicId: (string) $event->slug,
            nameEn: (string) $event->name_en,
            nameAr: (string) $event->name_ar,
            status: (string) $event->status,
            window: new MarketplaceEventWindow(
                (string) $event->timezone,
                DateTimeImmutable::createFromInterface($event->start_at),
                DateTimeImmutable::createFromInterface($event->end_at),
            ),
            creatorEligible: $event->created_by_user_id !== null,
        ));
    }
}
