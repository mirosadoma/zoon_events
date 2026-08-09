<?php

namespace App\Modules\AdminConsole\Http\Controllers\Public;

use App\Exceptions\FoundationException;
use App\Http\Controllers\Controller;
use App\Modules\Events\Application\Support\EvaluateEventCategoryCapacity;
use App\Modules\Events\Application\Support\EvaluatePublicRegistrationWindow;
use App\Modules\Events\Application\Support\EventZonePresenter;
use App\Modules\Events\Application\Support\PublicRegistrationEventPresenter;
use App\Modules\Events\Application\Support\RenderRegistrationInviteUnavailablePage;
use App\Modules\Events\Application\Support\RenderRegistrationSoldOutPage;
use App\Modules\Events\Application\Support\RenderRegistrationWindowUnavailablePage;
use App\Modules\Events\Application\Support\ResolveActiveRegistrationInvite;
use App\Modules\Events\Application\Support\ShareablePublicEventResolver;
use App\Modules\Events\Infrastructure\Persistence\Models\EventAgendaItem;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class PublicEventAgendaController extends Controller
{
    public function __construct(
        private readonly ShareablePublicEventResolver $events,
        private readonly PublicRegistrationEventPresenter $eventPages,
        private readonly ResolveActiveRegistrationInvite $invites,
        private readonly RenderRegistrationInviteUnavailablePage $inviteUnavailablePages,
        private readonly EvaluatePublicRegistrationWindow $registrationWindows,
        private readonly RenderRegistrationWindowUnavailablePage $registrationWindowPages,
        private readonly EvaluateEventCategoryCapacity $categoryCapacity,
        private readonly RenderRegistrationSoldOutPage $registrationSoldOutPages,
    ) {}

    public function show(Request $request, string $locale, string $eventSlug, ?string $inviteCode = null): Response
    {
        $event = $this->events->findBySlug($eventSlug);
        $resolvedLocale = $locale === 'ar' ? 'ar' : 'en';

        try {
            $invite = $this->invites->requireForPrivateEvent(
                $event,
                $inviteCode ?? $request->query('invite'),
            );
        } catch (FoundationException $exception) {
            if (str_starts_with($exception->problemCode, 'invite_')) {
                return $this->inviteUnavailablePages->execute($resolvedLocale, $event, $exception->problemCode);
            }

            throw $exception;
        }

        $window = $this->registrationWindows->status($event);
        if ($window !== EvaluatePublicRegistrationWindow::OPEN) {
            return $this->registrationWindowPages->execute($resolvedLocale, $event, $window);
        }

        if ($invite === null && $this->categoryCapacity->isEventFullyBooked($event)) {
            return $this->registrationSoldOutPages->execute($resolvedLocale, $event);
        }

        $event->loadMissing(['venues.zones', 'agendaItems.venue', 'agendaItems.zone']);

        $registerUrl = "/{$resolvedLocale}/events/{$event->slug}/register";
        if ($invite !== null) {
            $registerUrl .= '?invite='.$invite->code;
        }

        $fallbackVenueId = $event->venues->count() === 1
            ? (string) $event->venues->first()->id
            : null;

        $normalizedItems = $event->agendaItems
            ->map(fn (EventAgendaItem $item): array => EventZonePresenter::agendaItemForPublic(
                $item,
                (string) $event->timezone,
                $fallbackVenueId,
            ))
            ->sortBy([
                ['sort_order', 'asc'],
                ['start_at', 'asc'],
            ])
            ->values();

        $validVenueIds = $event->venues
            ->sortBy([
                ['sort_order', 'asc'],
                ['id', 'asc'],
            ])
            ->pluck('id')
            ->map(fn ($id): string => (string) $id)
            ->values()
            ->all();

        $defaultVenueId = $validVenueIds[0] ?? null;
        $requestedVenueId = $request->query('venue_id');
        $selectedVenueId = is_string($requestedVenueId) && in_array($requestedVenueId, $validVenueIds, true)
            ? $requestedVenueId
            : $defaultVenueId;

        $itemsForVenue = $selectedVenueId === null
            ? $normalizedItems
            : $normalizedItems->filter(
                fn (array $item): bool => ($item['event_venue_id'] ?? null) === $selectedVenueId,
            )->values();

        $zonesForVenue = $event->venues
            ->when($selectedVenueId !== null, fn ($venues) => $venues->where('id', (int) $selectedVenueId))
            ->flatMap(fn ($venue) => $venue->zones)
            ->map(fn ($zone): array => EventZonePresenter::toArray($zone))
            ->values()
            ->all();

        $validZoneIds = collect($zonesForVenue)->pluck('id')->all();
        $requestedZoneId = $request->query('zone_id');
        $selectedZoneId = is_string($requestedZoneId) && in_array($requestedZoneId, $validZoneIds, true)
            ? $requestedZoneId
            : null;

        $itemsForZone = $selectedZoneId === null
            ? $itemsForVenue
            : $itemsForVenue->filter(
                fn (array $item): bool => ($item['zone_id'] ?? null) === $selectedZoneId,
            )->values();

        $availableDates = $itemsForZone
            ->pluck('agenda_date')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        $requestedDate = $request->query('date');
        $selectedDate = is_string($requestedDate) && in_array($requestedDate, $availableDates, true)
            ? $requestedDate
            : ($availableDates[0] ?? null);

        $items = $itemsForZone
            ->filter(function (array $item) use ($selectedDate): bool {
                if ($selectedDate !== null && ($item['agenda_date'] ?? null) !== $selectedDate) {
                    return false;
                }

                return true;
            })
            ->map(fn (array $item): array => [
                'id' => $item['id'],
                'title' => $item['title'],
                'description' => $item['description'],
                'start_at' => $item['start_at'],
                'end_at' => $item['end_at'],
                'agenda_date' => $item['agenda_date'],
                'event_venue_id' => $item['event_venue_id'],
                'zone_id' => $item['zone_id'],
                'speaker' => $item['speaker'],
                'venue_name' => $item['venue_name'],
                'zone_name' => $item['zone_name'],
            ])
            ->values()
            ->all();

        return Inertia::render('public/registration/Agenda', [
            'locale' => $resolvedLocale,
            'event' => $this->eventPages->heroEvent($event),
            'items' => $items,
            'zones' => $zonesForVenue,
            'availableDates' => array_values($availableDates),
            'selectedVenueId' => $selectedVenueId,
            'selectedZoneId' => $selectedZoneId,
            'selectedDate' => $selectedDate,
            'registerUrl' => $registerUrl,
            'inviteCode' => $invite?->code,
        ]);
    }
}
