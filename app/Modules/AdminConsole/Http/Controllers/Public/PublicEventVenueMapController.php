<?php

namespace App\Modules\AdminConsole\Http\Controllers\Public;

use App\Exceptions\FoundationException;
use App\Http\Controllers\Controller;
use App\Modules\AdminConsole\Infrastructure\Persistence\Models\EventVenue;
use App\Modules\Events\Application\Support\EventVenueMapPresenter;
use App\Modules\Events\Application\Support\EventZonePresenter;
use App\Modules\Events\Application\Support\PublicRegistrationEventPresenter;
use App\Modules\Events\Application\Support\RenderRegistrationInviteUnavailablePage;
use App\Modules\Events\Application\Support\ResolveActiveRegistrationInvite;
use App\Modules\Events\Application\Support\ShareablePublicEventResolver;
use App\Modules\Events\Infrastructure\Persistence\Models\EventVenueMap;
use App\Modules\Events\Infrastructure\Persistence\Models\EventZone;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class PublicEventVenueMapController extends Controller
{
    public function __construct(
        private readonly ShareablePublicEventResolver $events,
        private readonly PublicRegistrationEventPresenter $eventPages,
        private readonly ResolveActiveRegistrationInvite $invites,
        private readonly RenderRegistrationInviteUnavailablePage $inviteUnavailablePages,
    ) {}

    public function show(Request $request, string $locale, string $eventSlug, string $venueId, ?string $inviteCode = null): Response
    {
        $event = $this->events->findBySlug($eventSlug);
        $resolvedLocale = $locale === 'ar' ? 'ar' : 'en';

        try {
            $this->invites->requireForPrivateEvent(
                $event,
                $inviteCode ?? $request->query('invite'),
            );
        } catch (FoundationException $exception) {
            if (str_starts_with($exception->problemCode, 'invite_')) {
                return $this->inviteUnavailablePages->execute($resolvedLocale, $event, $exception->problemCode);
            }

            throw $exception;
        }

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

        return Inertia::render('public/registration/VenueMap', [
            'locale' => $resolvedLocale,
            'event' => $this->eventPages->heroEvent($event),
            'venue' => [
                'id' => (string) $venue->id,
                'name' => ['en' => (string) $venue->name_en, 'ar' => (string) $venue->name_ar],
            ],
            'map' => $map instanceof EventVenueMap ? EventVenueMapPresenter::toArray($map) : null,
            'zones' => $zones,
        ]);
    }
}
