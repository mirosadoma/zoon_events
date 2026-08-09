<?php

namespace App\Modules\AdminConsole\Application;

use App\Models\User;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsLane;
use App\Modules\AdminConsole\Infrastructure\Persistence\Models\EventVenue;
use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\Audit\Infrastructure\Persistence\Models\AuditLog;
use App\Modules\Credentials\Infrastructure\Persistence\Models\Credential;
use App\Modules\Events\Application\Support\EventWallClockDateTime;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Kiosk\Infrastructure\Persistence\Models\Kiosk;
use App\Modules\Orders\Infrastructure\Persistence\Models\Order;
use App\Modules\Scanning\Infrastructure\Persistence\Models\ScanEvent;
use App\Modules\Tenancy\Domain\Context\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class DashboardOverviewBuilder
{
    /** @var list<string> */
    private const MARKER_COLORS = [
        '#0ea5e9', '#8b5cf6', '#10b981', '#f59e0b', '#ef4444',
        '#06b6d4', '#ec4899', '#84cc16', '#f97316', '#6366f1',
    ];

    /**
     * @return array<string, mixed>
     */
    public function build(?TenantContext $context): array
    {
        if ($context === null) {
            return $this->emptyOverview();
        }

        $tenantId = $context->tenant->id;
        $todayStart = now()->startOfDay();

        $eventsQuery = Event::query()->where('tenant_id', $tenantId);
        $eventIds = (clone $eventsQuery)->pluck('id');

        $recentAudit = AuditLog::query()
            ->where('tenant_id', $tenantId)
            ->latest('occurred_at')
            ->limit(5)
            ->get()
            ->map(function (AuditLog $log): array {
                $actorName = 'System';

                if ($log->actor_id) {
                    $actorName = User::query()->whereKey($log->actor_id)->value('name') ?? "User {$log->actor_id}";
                }

                return [
                    'id' => $log->id,
                    'actor' => $actorName,
                    'action' => $log->action,
                    'outcome' => $log->outcome,
                    'occurred_at' => $log->occurred_at?->toIso8601String(),
                ];
            })
            ->all();

        $registrations = Attendee::query()->whereIn('event_id', $eventIds)->count();
        $checkedIn = Attendee::query()
            ->whereIn('event_id', $eventIds)
            ->where(function ($query): void {
                $query->where('checkin_status', 'checked_in')
                    ->orWhereNotNull('first_checked_in_at');
            })
            ->count();
        $paidOrders = Order::query()
            ->whereIn('event_id', $eventIds)
            ->where('status', 'paid')
            ->count();
        $credentialsIssued = Credential::query()->whereIn('event_id', $eventIds)->count();

        return [
            'events_total' => (clone $eventsQuery)->count(),
            'events_published' => (clone $eventsQuery)->where('status', 'published')->count(),
            'attendees_total' => $registrations,
            'orders_total' => Order::query()->whereIn('event_id', $eventIds)->count(),
            'credentials_issued' => $credentialsIssued,
            'checkins_today' => ScanEvent::query()
                ->whereIn('event_id', $eventIds)
                ->where('result', 'accepted')
                ->where('scanned_at', '>=', $todayStart)
                ->count(),
            'kiosks_active' => Kiosk::query()
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->count(),
            'gates_active' => AcsLane::query()
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->count(),
            'scans_failed' => ScanEvent::query()
                ->whereIn('event_id', $eventIds)
                ->where('result', 'rejected')
                ->where('scanned_at', '>=', $todayStart)
                ->count(),
            'recent_audit_events' => $recentAudit,
            'registrations_by_day' => $this->registrationsByDay($eventIds->all()),
            'checkins_by_day' => $this->checkinsByDay($eventIds->all()),
            'orders_by_status' => $this->ordersByStatus($eventIds->all()),
            'funnel' => [
                'registered' => $registrations,
                'paid' => $paidOrders,
                'credentialed' => $credentialsIssued,
                'checked_in' => $checkedIn,
            ],
            'events_comparison' => $this->eventsComparison($tenantId),
            'published_venue_markers' => $this->publishedVenueMarkers($tenantId),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyOverview(): array
    {
        return [
            'events_total' => 0,
            'events_published' => 0,
            'attendees_total' => 0,
            'orders_total' => 0,
            'credentials_issued' => 0,
            'checkins_today' => 0,
            'kiosks_active' => 0,
            'gates_active' => 0,
            'scans_failed' => 0,
            'recent_audit_events' => [],
            'registrations_by_day' => [],
            'checkins_by_day' => [],
            'orders_by_status' => [],
            'funnel' => [
                'registered' => 0,
                'paid' => 0,
                'credentialed' => 0,
                'checked_in' => 0,
            ],
            'events_comparison' => [],
            'published_venue_markers' => [],
        ];
    }

    /**
     * @param  list<int|string>  $eventIds
     * @return list<array{date: string, count: int}>
     */
    private function registrationsByDay(array $eventIds): array
    {
        if ($eventIds === []) {
            return $this->emptyDaySeries();
        }

        $end = CarbonImmutable::now()->endOfDay();
        $start = $end->subDays(13)->startOfDay();
        $driver = DB::connection()->getDriverName();

        try {
            if ($driver === 'sqlite') {
                $rows = DB::table('attendees')
                    ->selectRaw("strftime('%Y-%m-%d', created_at) as day, COUNT(*) as aggregate_count")
                    ->whereIn('event_id', $eventIds)
                    ->whereBetween('created_at', [$start->toDateTimeString(), $end->toDateTimeString()])
                    ->groupBy('day')
                    ->pluck('aggregate_count', 'day');
            } else {
                $rows = DB::table('attendees')
                    ->selectRaw('DATE(created_at) as day, COUNT(*) as aggregate_count')
                    ->whereIn('event_id', $eventIds)
                    ->whereBetween('created_at', [$start->toDateTimeString(), $end->toDateTimeString()])
                    ->groupBy('day')
                    ->pluck('aggregate_count', 'day');
            }
        } catch (\Throwable) {
            return $this->emptyDaySeries();
        }

        return $this->fillDaySeries($start, $end, $rows->all());
    }

    /**
     * @param  list<int|string>  $eventIds
     * @return list<array{date: string, count: int}>
     */
    private function checkinsByDay(array $eventIds): array
    {
        if ($eventIds === []) {
            return $this->emptyDaySeries();
        }

        $end = CarbonImmutable::now()->endOfDay();
        $start = $end->subDays(13)->startOfDay();
        $driver = DB::connection()->getDriverName();

        try {
            if ($driver === 'sqlite') {
                $rows = DB::table('scan_events')
                    ->selectRaw("strftime('%Y-%m-%d', scanned_at) as day, COUNT(*) as aggregate_count")
                    ->whereIn('event_id', $eventIds)
                    ->whereIn('result', ['accepted', 'manual_override'])
                    ->whereBetween('scanned_at', [$start->toDateTimeString(), $end->toDateTimeString()])
                    ->groupBy('day')
                    ->pluck('aggregate_count', 'day');
            } else {
                $rows = DB::table('scan_events')
                    ->selectRaw('DATE(scanned_at) as day, COUNT(*) as aggregate_count')
                    ->whereIn('event_id', $eventIds)
                    ->whereIn('result', ['accepted', 'manual_override'])
                    ->whereBetween('scanned_at', [$start->toDateTimeString(), $end->toDateTimeString()])
                    ->groupBy('day')
                    ->pluck('aggregate_count', 'day');
            }
        } catch (\Throwable) {
            return $this->emptyDaySeries();
        }

        return $this->fillDaySeries($start, $end, $rows->all());
    }

    /**
     * @param  list<int|string>  $eventIds
     * @return list<array{status: string, count: int}>
     */
    private function ordersByStatus(array $eventIds): array
    {
        if ($eventIds === []) {
            return [];
        }

        return Order::query()
            ->whereIn('event_id', $eventIds)
            ->selectRaw('status, COUNT(*) as aggregate_count')
            ->groupBy('status')
            ->orderByDesc('aggregate_count')
            ->get()
            ->map(fn ($row): array => [
                'status' => (string) $row->status,
                'count' => (int) $row->aggregate_count,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function eventsComparison(string $tenantId): array
    {
        $events = Event::query()
            ->where('tenant_id', $tenantId)
            ->orderByDesc('start_at')
            ->limit(20)
            ->get(['id', 'name_en', 'name_ar', 'status', 'start_at', 'end_at']);

        if ($events->isEmpty()) {
            return [];
        }

        $ids = $events->pluck('id')->all();

        $attendeeCounts = Attendee::query()
            ->whereIn('event_id', $ids)
            ->selectRaw('event_id, COUNT(*) as aggregate_count')
            ->groupBy('event_id')
            ->pluck('aggregate_count', 'event_id');

        $checkedInCounts = Attendee::query()
            ->whereIn('event_id', $ids)
            ->where(function ($query): void {
                $query->where('checkin_status', 'checked_in')
                    ->orWhereNotNull('first_checked_in_at');
            })
            ->selectRaw('event_id, COUNT(*) as aggregate_count')
            ->groupBy('event_id')
            ->pluck('aggregate_count', 'event_id');

        $revenueByEvent = Order::query()
            ->whereIn('event_id', $ids)
            ->where('status', 'paid')
            ->selectRaw('event_id, COALESCE(SUM(total_minor), 0) as revenue_minor, MAX(currency) as currency')
            ->groupBy('event_id')
            ->get()
            ->keyBy('event_id');

        return $events->map(function (Event $event) use ($attendeeCounts, $checkedInCounts, $revenueByEvent): array {
            $attendees = (int) ($attendeeCounts[$event->id] ?? 0);
            $checkedIn = (int) ($checkedInCounts[$event->id] ?? 0);
            $revenue = $revenueByEvent->get($event->id);

            return [
                'id' => (string) $event->id,
                'name' => [
                    'en' => (string) $event->name_en,
                    'ar' => (string) $event->name_ar,
                ],
                'status' => (string) $event->status,
                'start_at' => EventWallClockDateTime::toIso8601($event->start_at, (string) $event->timezone),
                'end_at' => EventWallClockDateTime::toIso8601($event->end_at, (string) $event->timezone),
                'attendees' => $attendees,
                'checked_in' => $checkedIn,
                'checkin_rate' => $attendees > 0 ? round(($checkedIn / $attendees) * 100, 1) : null,
                'revenue_minor' => (int) ($revenue->revenue_minor ?? 0),
                'currency' => (string) ($revenue->currency ?? 'EGP'),
            ];
        })->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function publishedVenueMarkers(string $tenantId): array
    {
        $venues = EventVenue::query()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereHas('event', function ($query) use ($tenantId): void {
                $query->where('tenant_id', $tenantId)->where('status', 'published');
            })
            ->with(['event:id,name_en,name_ar,status,timezone,start_at,end_at'])
            ->orderBy('event_id')
            ->orderBy('sort_order')
            ->get();

        return $venues->map(function (EventVenue $venue): ?array {
            $event = $venue->event;
            if ($event === null) {
                return null;
            }

            $lat = $venue->latitude;
            $lng = $venue->longitude;
            if (! is_numeric($lat) || ! is_numeric($lng)) {
                return null;
            }

            $timezone = (string) $event->timezone;
            $start = $venue->start_at ?? $event->start_at;
            $end = $venue->end_at ?? $event->end_at;

            return [
                'event_id' => (string) $event->id,
                'event_name' => [
                    'en' => (string) $event->name_en,
                    'ar' => (string) $event->name_ar,
                ],
                'venue_id' => (string) $venue->id,
                'venue_name' => [
                    'en' => (string) ($venue->name_en ?: $venue->name_ar ?: ''),
                    'ar' => (string) ($venue->name_ar ?: $venue->name_en ?: ''),
                ],
                'latitude' => (float) $lat,
                'longitude' => (float) $lng,
                'start_at' => EventWallClockDateTime::toIso8601($start, $timezone),
                'end_at' => EventWallClockDateTime::toIso8601($end, $timezone),
                'timezone' => $timezone,
                'address' => $venue->location_address ? (string) $venue->location_address : null,
                'color' => $this->colorForKey((string) $event->id),
            ];
        })->filter()->values()->all();
    }

    private function colorForKey(string $key): string
    {
        $index = abs(crc32($key)) % count(self::MARKER_COLORS);

        return self::MARKER_COLORS[$index];
    }

    /**
     * @return list<array{date: string, count: int}>
     */
    private function emptyDaySeries(): array
    {
        $end = CarbonImmutable::now()->endOfDay();
        $start = $end->subDays(13)->startOfDay();

        return $this->fillDaySeries($start, $end, []);
    }

    /**
     * @param  array<string, int|string>  $countsByDay
     * @return list<array{date: string, count: int}>
     */
    private function fillDaySeries(CarbonImmutable $start, CarbonImmutable $end, array $countsByDay): array
    {
        $days = [];
        for ($cursor = $start; $cursor->lessThanOrEqualTo($end); $cursor = $cursor->addDay()) {
            $key = $cursor->toDateString();
            $days[] = [
                'date' => $key,
                'count' => (int) ($countsByDay[$key] ?? 0),
            ];
        }

        return $days;
    }
}
