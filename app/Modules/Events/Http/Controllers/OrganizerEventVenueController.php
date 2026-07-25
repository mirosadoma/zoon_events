<?php

namespace App\Modules\Events\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AdminConsole\Application\Actions\SyncEventVenues;
use App\Modules\AdminConsole\Infrastructure\Persistence\Models\EventVenue;
use App\Modules\Events\Contracts\EventScope;
use App\Modules\Events\Http\Requests\EventVenuesSyncRequest;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use InvalidArgumentException;
use Illuminate\Validation\ValidationException;

final class OrganizerEventVenueController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly TenantContextStore $contexts,
        private readonly EventScope $events,
    ) {}

    public function sync(EventVenuesSyncRequest $request, string $eventId, SyncEventVenues $action)
    {
        $tenantId = (string) $this->contexts->current()->tenant->id;
        abort_unless($this->events->exists($tenantId, $eventId), 404);

        $event = Event::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($eventId)
            ->firstOrFail();

        $venues = $request->validated('venues');

        try {
            $action->execute($tenantId, $event, $venues);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'venues' => [$exception->getMessage()],
            ]);
        }

        $saved = EventVenue::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $event->id)
            ->orderBy('sort_order')
            ->with(['country', 'city'])
            ->get()
            ->map(fn (EventVenue $venue): array => $this->mapVenue($venue))
            ->values()
            ->all();

        return $this->success(['venues' => $saved]);
    }

    private function mapVenue(EventVenue $venue): array
    {
        return [
            'id' => (string) $venue->id,
            'country_id' => (string) $venue->country_id,
            'city_id' => (string) $venue->city_id,
            'name' => ['en' => $venue->name_en, 'ar' => $venue->name_ar],
            'location_address' => $venue->location_address,
            'latitude' => $venue->latitude !== null ? (string) $venue->latitude : null,
            'longitude' => $venue->longitude !== null ? (string) $venue->longitude : null,
            'start_at' => $venue->start_at?->toIso8601String(),
            'end_at' => $venue->end_at?->toIso8601String(),
            'registration_opens_at' => $venue->registration_opens_at?->toIso8601String(),
            'registration_closes_at' => $venue->registration_closes_at?->toIso8601String(),
        ];
    }
}
