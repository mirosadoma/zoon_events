<?php

namespace App\Modules\Ai\Application\Chat;

use App\Modules\AdminConsole\Infrastructure\Persistence\Models\EventVenue;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Ticketing\Infrastructure\Persistence\Models\TicketType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final class PlatformRagContextBuilder
{
    private const CACHE_TTL_SECONDS = 120;

    public function build(int $tenantId, string $locale, int $limit = 10): string
    {
        $cacheKey = "ai.rag.platform.{$tenantId}.{$locale}.{$limit}";

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($tenantId, $locale, $limit): string {
            $events = Event::query()
                ->where('tenant_id', $tenantId)
                ->whereIn('status', ['published', 'draft', 'cancelled'])
                ->orderByDesc('start_at')
                ->limit($limit)
                ->get();

            if ($events->isEmpty()) {
                return 'No events found for this organization.';
            }

            $eventIds = $events->pluck('id')->all();

            $venues = EventVenue::query()
                ->where('tenant_id', $tenantId)
                ->whereIn('event_id', $eventIds)
                ->with('city')
                ->get()
                ->groupBy('event_id');

            $tickets = TicketType::query()
                ->where('tenant_id', $tenantId)
                ->whereIn('event_id', $eventIds)
                ->where('status', 'active')
                ->get()
                ->groupBy('event_id');

            $lines = [];

            foreach ($events as $event) {
                $title = $locale === 'ar' ? ($event->name_ar ?: $event->name_en) : ($event->name_en ?: $event->name_ar);
                $city = $this->resolveCity($venues->get($event->id), $locale);
                $date = $event->start_at?->format('Y-m-d') ?? 'TBD';

                $lines[] = "Event: {$title}, City: {$city}, Date: {$date}, Status: {$event->status}";

                $eventTickets = $tickets->get($event->id);
                if ($eventTickets !== null && $eventTickets->isNotEmpty()) {
                    $ticketNames = $eventTickets
                        ->map(fn (TicketType $ticket) => $locale === 'ar' ? $ticket->name_ar : $ticket->name_en)
                        ->filter()
                        ->take(3)
                        ->implode(', ');
                    if ($ticketNames !== '') {
                        $lines[] = "  Tickets: {$ticketNames}";
                    }
                }
            }

            return implode("\n", $lines);
        });
    }

    /**
     * @param  Collection<int, EventVenue>|null  $eventVenues
     */
    private function resolveCity($eventVenues, string $locale): string
    {
        if ($eventVenues === null || $eventVenues->isEmpty()) {
            return 'N/A';
        }

        $venue = $eventVenues->first();
        $city = $venue?->city;

        if ($city === null) {
            return 'N/A';
        }

        return $locale === 'ar'
            ? ($city->name_ar ?: $city->name_en)
            : ($city->name_en ?: $city->name_ar);
    }
}
