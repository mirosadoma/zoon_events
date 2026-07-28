<?php

namespace App\Modules\Events\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Events\Application\Actions\DeleteEventZoneFillImage;
use App\Modules\Events\Application\Actions\SyncEventZones;
use App\Modules\Events\Application\Actions\UpsertEventZoneFillImage;
use App\Modules\Events\Application\Support\EventZonePresenter;
use App\Modules\Events\Contracts\EventScope;
use App\Modules\Events\Http\Requests\ZoneFillImageUploadRequest;
use App\Modules\Events\Http\Requests\ZoneSyncRequest;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Events\Infrastructure\Persistence\Models\EventZone;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class OrganizerEventZoneController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly TenantContextStore $contexts,
        private readonly EventScope $events,
    ) {}

    public function sync(ZoneSyncRequest $request, string $eventId, SyncEventZones $action)
    {
        $tenantId = (string) $this->contexts->current()->tenant->id;
        abort_unless($this->events->exists($tenantId, $eventId), 404);

        $event = Event::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($eventId)
            ->firstOrFail();

        try {
            $action->execute(
                $tenantId,
                $event,
                (int) $request->validated('venue_id'),
                $request->validated('zones'),
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'zones' => [$exception->getMessage()],
            ]);
        }

        $saved = EventZone::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $event->id)
            ->where('venue_id', (int) $request->validated('venue_id'))
            ->orderBy('id')
            ->get()
            ->map(fn (EventZone $zone): array => EventZonePresenter::toArray($zone))
            ->values()
            ->all();

        return $this->success(['zones' => $saved]);
    }

    public function storeFillImage(
        ZoneFillImageUploadRequest $request,
        string $eventId,
        string $venueId,
        string $zoneId,
        UpsertEventZoneFillImage $action,
    ) {
        $tenantId = (string) $this->contexts->current()->tenant->id;
        abort_unless($this->events->exists($tenantId, $eventId), 404);

        $event = Event::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($eventId)
            ->firstOrFail();

        /** @var UploadedFile $image */
        $image = $request->file('image');

        try {
            $zone = $action->execute(
                $tenantId,
                $event,
                (int) $venueId,
                (int) $zoneId,
                $image,
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'image' => [$exception->getMessage()],
            ]);
        }

        return $this->success(['zone' => EventZonePresenter::toArray($zone)]);
    }

    public function destroyFillImage(
        string $eventId,
        string $venueId,
        string $zoneId,
        DeleteEventZoneFillImage $action,
    ) {
        $tenantId = (string) $this->contexts->current()->tenant->id;
        abort_unless($this->events->exists($tenantId, $eventId), 404);

        $event = Event::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($eventId)
            ->firstOrFail();

        try {
            $zone = $action->execute(
                $tenantId,
                $event,
                (int) $venueId,
                (int) $zoneId,
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'zone_id' => [$exception->getMessage()],
            ]);
        }

        return $this->success(['zone' => EventZonePresenter::toArray($zone)]);
    }
}
