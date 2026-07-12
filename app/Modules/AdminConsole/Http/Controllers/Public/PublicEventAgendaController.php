<?php

namespace App\Modules\AdminConsole\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Modules\Events\Application\Support\PublicRegistrationEventPresenter;
use App\Modules\Events\Application\Support\ShareablePublicEventResolver;
use App\Modules\Events\Infrastructure\Persistence\Models\EventAgendaItem;
use Inertia\Inertia;
use Inertia\Response;

final class PublicEventAgendaController extends Controller
{
    public function __construct(
        private readonly ShareablePublicEventResolver $events,
        private readonly PublicRegistrationEventPresenter $eventPages,
    ) {}

    public function show(string $locale, string $eventSlug): Response
    {
        $event = $this->events->findBySlug($eventSlug);
        $resolvedLocale = $locale === 'ar' ? 'ar' : 'en';
        $event->loadMissing('agendaItems');

        return Inertia::render('public/registration/Agenda', [
            'locale' => $resolvedLocale,
            'event' => $this->eventPages->heroEvent($event),
            'items' => $event->agendaItems
                ->map(fn (EventAgendaItem $item): array => [
                    'id' => (string) $item->id,
                    'title' => ['en' => $item->title_en, 'ar' => $item->title_ar],
                    'start_at' => $item->start_at?->toIso8601String(),
                    'end_at' => $item->end_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
            'registerUrl' => "/events/{$event->slug}/register",
        ]);
    }
}
