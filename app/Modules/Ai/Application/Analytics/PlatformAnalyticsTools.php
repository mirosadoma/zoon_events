<?php

namespace App\Modules\Ai\Application\Analytics;

use App\Modules\AdminConsole\Infrastructure\Persistence\Models\City;
use App\Modules\AdminConsole\Infrastructure\Persistence\Models\EventVenue;
use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Registration\Infrastructure\Persistence\Models\RegistrationSubmission;
use App\Modules\Ticketing\Infrastructure\Persistence\Models\TicketInventory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class PlatformAnalyticsTools
{
    private const CACHE_TTL_SECONDS = 60;

    public function __construct(
        private readonly int $tenantId,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function toolDefinitions(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_attendees_count',
                    'description' => 'Count registered attendees for the tenant, optionally filtered by city name.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'city' => [
                                'type' => 'string',
                                'description' => 'City name filter (English or Arabic), e.g. Cairo',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_events_count',
                    'description' => 'Count events for the tenant, optionally filtered by city where the event has a venue.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'city' => [
                                'type' => 'string',
                                'description' => 'City name filter (English or Arabic)',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_top_event',
                    'description' => 'Return the event with the highest registration count.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_tickets_sold',
                    'description' => 'Return total tickets sold for the tenant within a date range.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'date_range' => [
                                'type' => 'string',
                                'enum' => ['today', 'week', 'month', 'all_time'],
                                'description' => 'Date range for ticket sales',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function execute(string $name, array $arguments): array
    {
        return match ($name) {
            'get_attendees_count' => $this->getAttendeesCount(isset($arguments['city']) ? (string) $arguments['city'] : null),
            'get_events_count' => $this->getEventsCount(isset($arguments['city']) ? (string) $arguments['city'] : null),
            'get_top_event' => $this->getTopEvent(),
            'get_tickets_sold' => $this->getTicketsSold((string) ($arguments['date_range'] ?? 'all_time')),
            default => ['error' => 'unknown_function'],
        };
    }

    /**
     * @return array{type: string, label: string, value: int, city?: string}
     */
    public function getAttendeesCount(?string $city = null): array
    {
        $cacheKey = "ai.analytics.attendees.{$this->tenantId}.".md5($city ?? 'all');

        $count = Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($city): int {
            $query = Attendee::query()
                ->where('tenant_id', $this->tenantId)
                ->whereNull('cancelled_at');

            if ($city !== null && $city !== '') {
                $eventIds = $this->eventIdsForCity($city);
                if ($eventIds === []) {
                    return 0;
                }
                $query->whereIn('event_id', $eventIds);
            }

            return $query->count();
        });

        $result = [
            'type' => 'analytics',
            'label' => $city ? "Attendees in {$city}" : 'Total attendees',
            'value' => $count,
        ];

        if ($city) {
            $result['city'] = $city;
        }

        return $result;
    }

    /**
     * @return array{type: string, label: string, value: int, city?: string}
     */
    public function getEventsCount(?string $city = null): array
    {
        $cacheKey = "ai.analytics.events.{$this->tenantId}.".md5($city ?? 'all');

        $count = Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($city): int {
            $query = Event::query()->where('tenant_id', $this->tenantId);

            if ($city !== null && $city !== '') {
                $eventIds = $this->eventIdsForCity($city);
                if ($eventIds === []) {
                    return 0;
                }
                $query->whereIn('id', $eventIds);
            }

            return $query->count();
        });

        $result = [
            'type' => 'analytics',
            'label' => $city ? "Events in {$city}" : 'Total events',
            'value' => $count,
        ];

        if ($city) {
            $result['city'] = $city;
        }

        return $result;
    }

    /**
     * @return array{type: string, label: string, event_id: int, event_name: string, registrations: int}
     */
    public function getTopEvent(): array
    {
        $cacheKey = "ai.analytics.top_event.{$this->tenantId}";

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function (): array {
            $top = RegistrationSubmission::query()
                ->select('event_id', DB::raw('COUNT(*) as registrations'))
                ->where('tenant_id', $this->tenantId)
                ->groupBy('event_id')
                ->orderByDesc('registrations')
                ->first();

            if ($top === null) {
                return [
                    'type' => 'analytics',
                    'label' => 'Most popular event',
                    'event_id' => 0,
                    'event_name' => 'N/A',
                    'registrations' => 0,
                ];
            }

            $event = Event::query()
                ->where('tenant_id', $this->tenantId)
                ->find($top->event_id);

            return [
                'type' => 'analytics',
                'label' => 'Most popular event',
                'event_id' => (int) $top->event_id,
                'event_name' => $event?->name_en ?? 'Unknown',
                'registrations' => (int) $top->registrations,
            ];
        });
    }

    /**
     * @return array{type: string, label: string, value: int, date_range: string}
     */
    public function getTicketsSold(string $dateRange = 'all_time'): array
    {
        $cacheKey = "ai.analytics.tickets.{$this->tenantId}.{$dateRange}";

        $count = Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($dateRange): int {
            $dateFilter = $this->dateFilterForRange($dateRange);

            $inventorySum = (int) TicketInventory::query()
                ->where('tenant_id', $this->tenantId)
                ->sum('sold_quantity');

            if ($dateFilter === null) {
                return $inventorySum;
            }

            return (int) DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->where('order_items.tenant_id', $this->tenantId)
                ->where('orders.status', 'paid')
                ->where('orders.paid_at', '>=', $dateFilter)
                ->sum('order_items.quantity');
        });

        return [
            'type' => 'analytics',
            'label' => 'Tickets sold',
            'value' => $count,
            'date_range' => $dateRange,
        ];
    }

    /**
     * @return list<int>
     */
    private function eventIdsForCity(string $city): array
    {
        $city = trim($city);
        if ($city === '') {
            return [];
        }

        $cityIds = City::query()
            ->where(function ($query) use ($city): void {
                $query->where('name_en', 'like', '%'.$city.'%')
                    ->orWhere('name_ar', 'like', '%'.$city.'%');
            })
            ->pluck('id');

        if ($cityIds->isEmpty()) {
            return [];
        }

        return EventVenue::query()
            ->where('tenant_id', $this->tenantId)
            ->whereIn('city_id', $cityIds)
            ->distinct()
            ->pluck('event_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function dateFilterForRange(string $dateRange): ?\DateTimeInterface
    {
        return match ($dateRange) {
            'today' => now()->startOfDay(),
            'week' => now()->subWeek(),
            'month' => now()->subMonth(),
            default => null,
        };
    }
}
