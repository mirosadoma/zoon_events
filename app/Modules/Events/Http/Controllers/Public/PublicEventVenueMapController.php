<?php

namespace App\Modules\Events\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Modules\AdminConsole\Infrastructure\Persistence\Models\EventVenue;
use App\Modules\Events\Application\Queries\GetPublicEvent;
use App\Modules\Events\Application\Support\EventPathPresenter;
use App\Modules\Events\Application\Support\EventVenueMapPresenter;
use App\Modules\Events\Application\Support\EventZonePresenter;
use App\Modules\Events\Domain\Context\PublicEventContextStore;
use App\Modules\Events\Infrastructure\Persistence\Models\EventPath;
use App\Modules\Events\Infrastructure\Persistence\Models\EventVenueMap;
use App\Modules\Events\Infrastructure\Persistence\Models\EventZone;
use App\Modules\Shared\Http\Responses\RespondsWithApi;

final class PublicEventVenueMapController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly PublicEventContextStore $contexts,
    ) {}

    public function show(string $eventSlug, string $venueId, GetPublicEvent $events)
    {
        $event = $events->execute($this->contexts->current());

        $venue = EventVenue::query()
            ->where('tenant_id', $event->tenant_id)
            ->where('event_id', $event->id)
            ->whereKey((int) $venueId)
            ->firstOrFail();

        $map = EventVenueMap::query()
            ->where('tenant_id', $event->tenant_id)
            ->where('event_id', $event->id)
            ->where('venue_id', $venue->id)
            ->first();

        $zones = EventZone::query()
            ->where('tenant_id', $event->tenant_id)
            ->where('event_id', $event->id)
            ->where('venue_id', $venue->id)
            ->whereNotNull('shape_type')
            ->whereNotNull('polygon_coordinates')
            ->orderBy('id')
            ->get()
            ->map(fn (EventZone $zone): array => EventZonePresenter::toPublicMapArray(
                $zone,
                $venue->latitude !== null ? (float) $venue->latitude : null,
                $venue->longitude !== null ? (float) $venue->longitude : null,
            ))
            ->values()
            ->all();

        $paths = EventPath::query()
            ->where('tenant_id', $event->tenant_id)
            ->where('event_id', $event->id)
            ->where('venue_id', $venue->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (EventPath $path): array => EventPathPresenter::toPublicMapArray($path))
            ->values()
            ->all();

        return $this->success([
            'map' => $map instanceof EventVenueMap ? EventVenueMapPresenter::toArray($map) : null,
            'zones' => $zones,
            'paths' => $paths,
            'venue' => [
                'id' => (string) $venue->id,
                'name' => ['en' => (string) $venue->name_en, 'ar' => (string) $venue->name_ar],
                'latitude' => $venue->latitude !== null ? (float) $venue->latitude : null,
                'longitude' => $venue->longitude !== null ? (float) $venue->longitude : null,
            ],
        ]);
    }
}
