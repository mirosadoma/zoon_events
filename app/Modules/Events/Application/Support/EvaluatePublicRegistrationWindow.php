<?php

namespace App\Modules\Events\Application\Support;

use App\Modules\AdminConsole\Infrastructure\Persistence\Models\EventVenue;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use Carbon\CarbonImmutable;

final class EvaluatePublicRegistrationWindow
{
    public const OPEN = 'open';

    public const NOT_OPEN = 'not_open';

    public const CLOSED = 'closed';

    /**
     * Datetime check in the event timezone:
     * open when opens_at <= now <= closes_at (null bounds are ignored).
     *
     * Uses event-level registration window, falling back to min/max across venues.
     *
     * @return self::OPEN|self::NOT_OPEN|self::CLOSED
     */
    public function status(Event $event, ?CarbonImmutable $now = null): string
    {
        $timezone = is_string($event->timezone) && $event->timezone !== ''
            ? $event->timezone
            : (string) config('app.timezone', 'UTC');

        $moment = ($now ?? CarbonImmutable::now($timezone))->timezone($timezone);

        [$opens, $closes] = $this->resolveWindow($event, $timezone);

        if ($opens === null && $closes === null) {
            return self::OPEN;
        }

        if ($opens !== null && $moment->lt($opens)) {
            return self::NOT_OPEN;
        }

        if ($closes !== null && $moment->gt($closes)) {
            return self::CLOSED;
        }

        return self::OPEN;
    }

    public function isOpen(Event $event, ?CarbonImmutable $now = null): bool
    {
        return $this->status($event, $now) === self::OPEN;
    }

    /**
     * @return array{0:?CarbonImmutable,1:?CarbonImmutable}
     */
    private function resolveWindow(Event $event, string $timezone): array
    {
        $opens = $event->registration_opens_at?->timezone($timezone);
        $closes = $event->registration_closes_at?->timezone($timezone);

        if ($opens !== null || $closes !== null) {
            return [$opens, $closes];
        }

        if ($event->id === null) {
            return [null, null];
        }

        $venues = EventVenue::query()
            ->where('event_id', $event->id)
            ->where(function ($query): void {
                $query->whereNotNull('registration_opens_at')
                    ->orWhereNotNull('registration_closes_at');
            })
            ->get(['registration_opens_at', 'registration_closes_at']);

        if ($venues->isEmpty()) {
            return [null, null];
        }

        $openTimes = $venues
            ->pluck('registration_opens_at')
            ->filter()
            ->map(fn ($value): CarbonImmutable => CarbonImmutable::parse($value)->timezone($timezone));
        $closeTimes = $venues
            ->pluck('registration_closes_at')
            ->filter()
            ->map(fn ($value): CarbonImmutable => CarbonImmutable::parse($value)->timezone($timezone));

        return [
            $openTimes->isEmpty() ? null : $openTimes->min(),
            $closeTimes->isEmpty() ? null : $closeTimes->max(),
        ];
    }
}
