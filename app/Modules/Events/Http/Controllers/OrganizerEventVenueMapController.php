<?php

namespace App\Modules\Events\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AdminConsole\Infrastructure\Persistence\Models\EventVenue;
use App\Modules\Events\Application\Actions\ConvertRelativeMapGeometry;
use App\Modules\Events\Application\Actions\DeleteEventVenueMap;
use App\Modules\Events\Application\Actions\UpdateEventVenueMapSettings;
use App\Modules\Events\Application\Actions\UpsertEventVenueMap;
use App\Modules\Events\Application\Support\EventVenueMapPresenter;
use App\Modules\Events\Application\Support\EventZonePresenter;
use App\Modules\Events\Contracts\EventScope;
use App\Modules\Events\Http\Requests\VenueMapSettingsRequest;
use App\Modules\Events\Http\Requests\VenueMapUploadRequest;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Events\Infrastructure\Persistence\Models\EventVenueMap;
use App\Modules\Events\Infrastructure\Persistence\Models\EventZone;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class OrganizerEventVenueMapController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly TenantContextStore $contexts,
        private readonly EventScope $events,
    ) {}

    public function show(string $eventId, string $venueId)
    {
        $tenantId = (string) $this->contexts->current()->tenant->id;
        abort_unless($this->events->exists($tenantId, $eventId), 404);

        $venue = $this->resolveVenue($tenantId, $eventId, (int) $venueId);

        return $this->success($this->payload($tenantId, $eventId, $venue));
    }

    public function store(VenueMapUploadRequest $request, string $eventId, string $venueId, UpsertEventVenueMap $action)
    {
        $tenantId = (string) $this->contexts->current()->tenant->id;
        abort_unless($this->events->exists($tenantId, $eventId), 404);

        $event = Event::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($eventId)
            ->firstOrFail();

        $venue = $this->resolveVenue($tenantId, $eventId, (int) $venueId);

        /** @var UploadedFile $image */
        $image = $request->file('image');

        try {
            $map = $action->execute(
                $tenantId,
                $event,
                (int) $venue->id,
                $image,
                $request->validated('width'),
                $request->validated('height'),
                [
                    'overlay_north' => $request->validated('overlay_north'),
                    'overlay_south' => $request->validated('overlay_south'),
                    'overlay_east' => $request->validated('overlay_east'),
                    'overlay_west' => $request->validated('overlay_west'),
                    'map_center_lat' => $request->validated('map_center_lat'),
                    'map_center_lng' => $request->validated('map_center_lng'),
                    'map_zoom' => $request->validated('map_zoom'),
                    'map_heading' => $request->validated('map_heading'),
                    'map_type' => $request->validated('map_type'),
                ],
            );
            $aspect = ($map->width && $map->height && $map->height > 0)
                ? ((float) $map->width / (float) $map->height)
                : 1.6;
            UpdateEventVenueMapSettings::ensureOverlayBounds($map, $aspect);
            (new ConvertRelativeMapGeometry)->execute($map->refresh());
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'image' => [$exception->getMessage()],
            ]);
        }

        return $this->success($this->payload($tenantId, $eventId, $venue->fresh(['zones']) ?? $venue));
    }

    public function updateSettings(
        VenueMapSettingsRequest $request,
        string $eventId,
        string $venueId,
        UpdateEventVenueMapSettings $action,
    ) {
        $tenantId = (string) $this->contexts->current()->tenant->id;
        abort_unless($this->events->exists($tenantId, $eventId), 404);

        $event = Event::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($eventId)
            ->firstOrFail();

        $venue = $this->resolveVenue($tenantId, $eventId, (int) $venueId);

        try {
            $action->execute(
                $tenantId,
                $event,
                (int) $venue->id,
                $request->validated(),
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'map_center_lat' => [$exception->getMessage()],
            ]);
        }

        return $this->success($this->payload($tenantId, $eventId, $venue->fresh(['zones']) ?? $venue));
    }

    public function destroy(string $eventId, string $venueId, DeleteEventVenueMap $action)
    {
        $tenantId = (string) $this->contexts->current()->tenant->id;
        abort_unless($this->events->exists($tenantId, $eventId), 404);

        $event = Event::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($eventId)
            ->firstOrFail();

        $venue = $this->resolveVenue($tenantId, $eventId, (int) $venueId);

        try {
            $action->execute($tenantId, $event, (int) $venue->id);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'venue_id' => [$exception->getMessage()],
            ]);
        }

        return $this->success($this->payload($tenantId, $eventId, $venue->fresh(['zones']) ?? $venue));
    }

    public function zones(string $eventId, string $venueId)
    {
        $tenantId = (string) $this->contexts->current()->tenant->id;
        abort_unless($this->events->exists($tenantId, $eventId), 404);

        $venue = $this->resolveVenue($tenantId, $eventId, (int) $venueId);

        $zones = EventZone::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('venue_id', $venue->id)
            ->orderBy('id')
            ->get()
            ->map(fn (EventZone $zone): array => EventZonePresenter::toArray($zone))
            ->values()
            ->all();

        return $this->success(['zones' => $zones]);
    }

    private function resolveVenue(string $tenantId, string $eventId, int $venueId): EventVenue
    {
        return EventVenue::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->whereKey($venueId)
            ->with('zones')
            ->firstOrFail();
    }

    /**
     * @return array{map: ?array<string, mixed>, zones: list<array<string, mixed>>, venue: array<string, mixed>}
     */
    private function payload(string $tenantId, string $eventId, EventVenue $venue): array
    {
        $map = EventVenueMap::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('venue_id', $venue->id)
            ->first();

        $zones = ($venue->relationLoaded('zones') ? $venue->zones : $venue->zones()->orderBy('id')->get())
            ->map(fn (EventZone $zone): array => EventZonePresenter::toArray($zone))
            ->values()
            ->all();

        return [
            'map' => $map instanceof EventVenueMap ? EventVenueMapPresenter::toArray($map) : null,
            'zones' => $zones,
            'venue' => [
                'id' => (string) $venue->id,
                'name' => ['en' => (string) $venue->name_en, 'ar' => (string) $venue->name_ar],
                'latitude' => $venue->latitude !== null ? (float) $venue->latitude : null,
                'longitude' => $venue->longitude !== null ? (float) $venue->longitude : null,
            ],
        ];
    }
}
