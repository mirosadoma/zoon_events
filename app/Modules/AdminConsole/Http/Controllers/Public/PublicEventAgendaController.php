<?php

namespace App\Modules\AdminConsole\Http\Controllers\Public;

use App\Exceptions\FoundationException;
use App\Http\Controllers\Controller;
use App\Modules\Events\Application\Support\EvaluateEventCategoryCapacity;
use App\Modules\Events\Application\Support\EvaluatePublicRegistrationWindow;
use App\Modules\Events\Application\Support\EventWallClockDateTime;
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

        $event->loadMissing(['agendaItems.venue']);

        $registerUrl = "/{$resolvedLocale}/events/{$event->slug}/register";
        if ($invite !== null) {
            $registerUrl .= '?invite='.$invite->code;
        }

        $fallbackVenueId = $event->venues()->count() === 1
            ? (string) $event->venues()->value('id')
            : null;

        $normalizedItems = $event->agendaItems
            ->map(function (EventAgendaItem $item) use ($event, $fallbackVenueId): array {
                $agendaDate = $item->agenda_date?->toDateString()
                    ?? ($item->start_at?->toDateString());
                $venueId = $item->event_venue_id !== null
                    ? (string) $item->event_venue_id
                    : $fallbackVenueId;

                return [
                    'id' => (string) $item->id,
                    'title' => ['en' => $item->title_en, 'ar' => $item->title_ar],
                    'start_at' => EventWallClockDateTime::toIso8601($item->start_at, $event->timezone),
                    'end_at' => EventWallClockDateTime::toIso8601($item->end_at, $event->timezone),
                    'agenda_date' => $agendaDate,
                    'event_venue_id' => $venueId,
                    'venue_name' => $item->venue
                        ? ['en' => (string) $item->venue->name_en, 'ar' => (string) $item->venue->name_ar]
                        : null,
                    'sort_order' => (int) $item->sort_order,
                ];
            })
            ->sortBy([
                ['sort_order', 'asc'],
                ['start_at', 'asc'],
            ])
            ->values();

        $validVenueIds = $event->venues()
            ->orderBy('sort_order')
            ->orderBy('id')
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

        $availableDates = $itemsForVenue
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

        $items = $itemsForVenue
            ->filter(function (array $item) use ($selectedDate): bool {
                if ($selectedDate !== null && ($item['agenda_date'] ?? null) !== $selectedDate) {
                    return false;
                }

                return true;
            })
            ->map(fn (array $item): array => [
                'id' => $item['id'],
                'title' => $item['title'],
                'start_at' => $item['start_at'],
                'end_at' => $item['end_at'],
                'agenda_date' => $item['agenda_date'],
                'event_venue_id' => $item['event_venue_id'],
                'venue_name' => $item['venue_name'],
            ])
            ->values()
            ->all();

        return Inertia::render('public/registration/Agenda', [
            'locale' => $resolvedLocale,
            'event' => $this->eventPages->heroEvent($event),
            'items' => $items,
            'availableDates' => array_values($availableDates),
            'selectedVenueId' => $selectedVenueId,
            'selectedDate' => $selectedDate,
            'registerUrl' => $registerUrl,
            'inviteCode' => $invite?->code,
        ]);
    }
}
