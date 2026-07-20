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

        $event->loadMissing('agendaItems');

        $registerUrl = "/{$resolvedLocale}/events/{$event->slug}/register";
        if ($invite !== null) {
            $registerUrl .= '?invite='.$invite->code;
        }

        return Inertia::render('public/registration/Agenda', [
            'locale' => $resolvedLocale,
            'event' => $this->eventPages->heroEvent($event),
            'items' => $event->agendaItems
                ->map(fn (EventAgendaItem $item): array => [
                    'id' => (string) $item->id,
                    'title' => ['en' => $item->title_en, 'ar' => $item->title_ar],
                    'start_at' => EventWallClockDateTime::toIso8601($item->start_at, $event->timezone),
                    'end_at' => EventWallClockDateTime::toIso8601($item->end_at, $event->timezone),
                ])
                ->values()
                ->all(),
            'registerUrl' => $registerUrl,
            'inviteCode' => $invite?->code,
        ]);
    }
}
