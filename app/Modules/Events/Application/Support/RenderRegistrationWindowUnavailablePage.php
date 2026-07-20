<?php

namespace App\Modules\Events\Application\Support;

use App\Modules\AdminConsole\Infrastructure\Persistence\Models\EventVenue;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response;

final class RenderRegistrationWindowUnavailablePage
{
    public function __construct(
        private readonly EvaluatePublicRegistrationWindow $windows,
    ) {}

    public function execute(string $locale, Event $event, ?string $status = null): Response
    {
        $resolvedLocale = $locale === 'ar' ? 'ar' : 'en';
        $resolvedStatus = $status ?? $this->windows->status($event);
        $statusKey = $resolvedStatus === EvaluatePublicRegistrationWindow::CLOSED
            ? 'closed'
            : 'not_open';

        [$opensAt, $closesAt] = $this->displayWindow($event);

        return Inertia::render('public/registration/RegistrationWindow', [
            'locale' => $resolvedLocale,
            'status' => $statusKey,
            'event' => [
                'slug' => $event->slug,
                'name' => [
                    'en' => $event->name_en,
                    'ar' => $event->name_ar,
                ],
                'registration_opens_at' => $opensAt,
                'registration_closes_at' => $closesAt,
                'timezone' => $event->timezone,
            ],
        ]);
    }

    /** @return array{0:?string,1:?string} */
    private function displayWindow(Event $event): array
    {
        $opens = $event->registration_opens_at;
        $closes = $event->registration_closes_at;

        if ($opens === null && $closes === null && $event->id !== null) {
            $venues = EventVenue::query()
                ->where('event_id', $event->id)
                ->get(['registration_opens_at', 'registration_closes_at']);

            $openDates = $venues->pluck('registration_opens_at')->filter();
            $closeDates = $venues->pluck('registration_closes_at')->filter();

            $opens = $openDates->isEmpty() ? null : CarbonImmutable::parse($openDates->min());
            $closes = $closeDates->isEmpty() ? null : CarbonImmutable::parse($closeDates->max());
        }

        return [
            EventWallClockDateTime::toIso8601($opens, (string) $event->timezone),
            EventWallClockDateTime::toIso8601($closes, (string) $event->timezone),
        ];
    }
}
