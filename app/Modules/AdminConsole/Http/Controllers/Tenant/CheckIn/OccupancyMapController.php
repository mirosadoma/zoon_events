<?php

namespace App\Modules\AdminConsole\Http\Controllers\Tenant\CheckIn;

use App\Http\Controllers\Controller;
use App\Modules\AdminConsole\Application\SessionContextBuilder;
use App\Modules\AdminConsole\Http\Controllers\Tenant\CheckIn\Concerns\AuthorizesTenantEventPage;
use App\Modules\AdminConsole\Infrastructure\Persistence\Models\EventVenue;
use App\Modules\Authorization\Application\PermissionEvaluator;
use App\Modules\Events\Application\Support\EventVenueMapPresenter;
use App\Modules\Events\Application\Support\EventZonePresenter;
use App\Modules\Events\Infrastructure\Persistence\Models\EventVenueMap;
use App\Modules\Events\Infrastructure\Persistence\Models\EventZone;
use App\Modules\Scanning\Application\Queries\GetEventZoneOccupancyQuery;
use Inertia\Inertia;
use Inertia\Response;

final class OccupancyMapController extends Controller
{
    use AuthorizesTenantEventPage;

    public function __construct(
        private readonly SessionContextBuilder $sessions,
        private readonly PermissionEvaluator $permissions,
        private readonly GetEventZoneOccupancyQuery $occupancy,
    ) {}

    public function show(string $eventId): Response
    {
        [$context, $event] = $this->authorizeTenantEvent(
            $this->sessions,
            $this->permissions,
            $eventId,
            'checkin.dashboard.view',
        );

        $tenantId = $context->tenant->id;

        $venue = EventVenue::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $event->id)
            ->orderBy('id')
            ->first();

        $map = null;
        $zones = [];
        $venuePayload = null;

        if ($venue instanceof EventVenue) {
            $mapModel = EventVenueMap::query()
                ->where('tenant_id', $tenantId)
                ->where('event_id', $event->id)
                ->where('venue_id', $venue->id)
                ->first();

            $map = $mapModel instanceof EventVenueMap
                ? EventVenueMapPresenter::toArray($mapModel)
                : null;

            $zones = EventZone::query()
                ->where('tenant_id', $tenantId)
                ->where('event_id', $event->id)
                ->where('venue_id', $venue->id)
                ->orderBy('id')
                ->get()
                ->map(fn (EventZone $zone): array => EventZonePresenter::toArray($zone))
                ->values()
                ->all();

            $venuePayload = [
                'id' => (string) $venue->id,
                'name' => ['en' => (string) $venue->name_en, 'ar' => (string) $venue->name_ar],
                'latitude' => $venue->latitude !== null ? (float) $venue->latitude : null,
                'longitude' => $venue->longitude !== null ? (float) $venue->longitude : null,
            ];
        }

        return Inertia::render('tenant/checkin/OccupancyMap', [
            'event' => [
                'id' => $event->id,
                'name' => ['en' => $event->name_en, 'ar' => $event->name_ar],
            ],
            'tenantId' => $tenantId,
            'venue' => $venuePayload,
            'map' => $map,
            'zones' => $zones,
            'initialSummary' => $this->occupancy->summary($tenantId, (string) $event->id),
            'initialAnalytics' => $this->occupancy->analytics($tenantId, (string) $event->id),
        ]);
    }
}
