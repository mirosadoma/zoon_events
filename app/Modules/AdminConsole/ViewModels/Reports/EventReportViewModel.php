<?php

namespace App\Modules\AdminConsole\ViewModels\Reports;

use App\Modules\AccessControl\Infrastructure\Persistence\Models\AccessEvent;
use App\Modules\AdminConsole\Infrastructure\Persistence\Models\EventVenue;
use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\BadgePrinting\Infrastructure\Persistence\Models\BadgePrintJob;
use App\Modules\Credentials\Infrastructure\Persistence\Models\Credential;
use App\Modules\Events\Application\Support\EventWallClockDateTime;
use App\Modules\Events\Domain\RegistrationMode;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Events\Infrastructure\Persistence\Models\EventCategory;
use App\Modules\Events\Infrastructure\Persistence\Models\EventRegistrationInvite;
use App\Modules\Kiosk\Domain\KioskStatusDeriver;
use App\Modules\Kiosk\Infrastructure\Persistence\Models\Kiosk;
use App\Modules\Orders\Infrastructure\Persistence\Models\Order;
use App\Modules\Scanning\Infrastructure\Persistence\Models\EventCheckInSetting;
use App\Modules\Scanning\Infrastructure\Persistence\Models\ScanEvent;
use App\Modules\Shared\Contracts\Clock;
use App\Modules\Ticketing\Infrastructure\Persistence\Models\TicketType;
use App\Modules\WalletPasses\Infrastructure\Persistence\Models\WalletPass;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class EventReportViewModel
{
    public function __construct(
        private KioskStatusDeriver $kioskStatus,
        private Clock $clock,
    ) {}

    /**
     * @return array{event: array<string, mixed>, tenantId: string, report: array<string, mixed>}
     */
    public function make(Event $event, string $tenantId): array
    {
        $eventId = $event->id;
        $timezone = is_string($event->timezone) && $event->timezone !== ''
            ? $event->timezone
            : 'UTC';

        $registrations = Attendee::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->count();

        $checkedInAttendees = Attendee::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where(function ($query): void {
                $query->where('checkin_status', 'checked_in')
                    ->orWhereNotNull('first_checked_in_at');
            })
            ->count();

        $ordersByStatus = Order::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->selectRaw('status, COUNT(*) as aggregate_count, COALESCE(SUM(total_minor), 0) as revenue_minor')
            ->groupBy('status')
            ->get()
            ->map(fn ($row): array => [
                'status' => (string) $row->status,
                'count' => (int) $row->aggregate_count,
                'revenue_minor' => (int) $row->revenue_minor,
            ])
            ->values()
            ->all();

        $ordersTotal = array_sum(array_column($ordersByStatus, 'count'));
        $paidOrders = collect($ordersByStatus)->firstWhere('status', 'paid');
        $paidOrderCount = (int) ($paidOrders['count'] ?? 0);
        $paidRevenueMinor = (int) ($paidOrders['revenue_minor'] ?? 0);

        $currency = Order::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('status', 'paid')
            ->whereNotNull('currency')
            ->value('currency')
            ?? Order::query()
                ->where('tenant_id', $tenantId)
                ->where('event_id', $eventId)
                ->whereNotNull('currency')
                ->value('currency')
            ?? 'EGP';

        $credentialsIssued = Credential::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->count();

        $credentialsRevoked = Credential::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('status', 'revoked')
            ->count();

        $walletPasses = WalletPass::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->count();

        $acceptedScans = ScanEvent::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->whereIn('result', ['accepted', 'manual_override'])
            ->count();

        $rejectedScans = ScanEvent::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('result', 'rejected')
            ->count();

        $scanTotal = $acceptedScans + $rejectedScans;

        $badgeByStatus = BadgePrintJob::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->selectRaw('status, COUNT(*) as aggregate_count')
            ->groupBy('status')
            ->pluck('aggregate_count', 'status')
            ->map(fn ($count): int => (int) $count)
            ->all();

        $badgeReprints = BadgePrintJob::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('is_reprint', true)
            ->count();

        $acsAccepted = AccessEvent::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('decision', 'allow')
            ->count();

        $acsRejected = AccessEvent::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('decision', 'deny')
            ->count();

        $firstScanSuccessRate = $this->firstScanSuccessRate($tenantId, (string) $eventId);
        $checkInRate = $registrations > 0
            ? round(($checkedInAttendees / $registrations) * 100, 1)
            : null;
        $kioskCounts = $this->kioskCounts($tenantId, (string) $eventId);
        $categories = $this->categoryBreakdown($tenantId, (string) $eventId);
        $ticketTypes = $this->ticketTypeBreakdown($tenantId, (string) $eventId);
        $rejectReasons = $this->topRejectReasons($tenantId, (string) $eventId, $rejectedScans);
        $checkinsByHour = $this->checkinsByHour($tenantId, (string) $eventId, $timezone, $event);
        $peakHour = collect($checkinsByHour)
            ->sortByDesc(fn (array $row): int => $row['accepted_scans'])
            ->first();
        $funnel = $this->funnel(
            $event,
            $tenantId,
            (string) $eventId,
            $registrations,
            $paidOrderCount,
            $credentialsIssued,
            $checkedInAttendees,
        );
        $venueMarkers = $this->venueMarkers($tenantId, (string) $eventId);

        return [
            'event' => [
                'id' => $event->id,
                'name' => ['en' => $event->name_en, 'ar' => $event->name_ar],
                'timezone' => $timezone,
                'status' => $event->status,
                'start_at' => EventWallClockDateTime::toIso8601($event->start_at, $timezone),
                'end_at' => EventWallClockDateTime::toIso8601($event->end_at, $timezone),
            ],
            'tenantId' => $tenantId,
            'report' => [
                'summary' => [
                    'registrations' => $this->metric($registrations),
                    'checked_in_attendees' => $this->metric($checkedInAttendees),
                    'checkin_rate' => $this->metric($checkInRate, $registrations > 0),
                    'paid_orders' => $this->metric($paidOrderCount),
                    'payment_success_rate' => $this->metric(
                        $ordersTotal > 0 ? round(($paidOrderCount / $ordersTotal) * 100, 1) : null,
                        $ordersTotal > 0,
                    ),
                    'revenue_minor' => $this->metric($paidRevenueMinor, true),
                    'currency' => $currency,
                    'credentials_issued' => $this->metric($credentialsIssued),
                    'credentials_revoked' => $this->metric($credentialsRevoked),
                    'wallet_adoption' => $this->metric(
                        $credentialsIssued > 0 ? round(($walletPasses / $credentialsIssued) * 100, 1) : null,
                        $credentialsIssued > 0,
                    ),
                    'accepted_scans' => $this->metric($acceptedScans),
                    'rejected_scans' => $this->metric($rejectedScans),
                    'checkin_success_rate' => $this->metric(
                        $scanTotal > 0 ? round(($acceptedScans / $scanTotal) * 100, 1) : null,
                        $scanTotal > 0,
                    ),
                    'first_scan_success_rate' => $this->metric(
                        $firstScanSuccessRate['rate'],
                        $firstScanSuccessRate['available'],
                    ),
                    'badge_prints' => $this->metric((int) ($badgeByStatus['printed'] ?? 0)),
                    'badge_failed' => $this->metric((int) ($badgeByStatus['failed'] ?? 0)),
                    'badge_reprints' => $this->metric($badgeReprints),
                    'acs_entries_accepted' => $this->metric($acsAccepted),
                    'acs_entries_rejected' => $this->metric($acsRejected),
                    'kiosks_online' => $this->metric($kioskCounts['online']),
                    'kiosks_total' => $this->metric($kioskCounts['total']),
                    'peak_hour' => $this->metric(
                        is_array($peakHour) && ($peakHour['accepted_scans'] ?? 0) > 0
                            ? $peakHour['hour']
                            : null,
                        is_array($peakHour) && ($peakHour['accepted_scans'] ?? 0) > 0,
                    ),
                    'peak_hour_scans' => $this->metric(
                        is_array($peakHour) ? (int) $peakHour['accepted_scans'] : null,
                        is_array($peakHour) && ($peakHour['accepted_scans'] ?? 0) > 0,
                    ),
                ],
                // Backward-compatible flat keys used by older UI expectations / tests.
                'registrations' => $this->metric($registrations),
                'paid_orders' => $this->metric($paidOrderCount),
                'payment_success_rate' => $this->metric(
                    $ordersTotal > 0 ? round(($paidOrderCount / $ordersTotal) * 100, 1) : null,
                    $ordersTotal > 0,
                ),
                'credentials_issued' => $this->metric($credentialsIssued),
                'credentials_revoked' => $this->metric($credentialsRevoked),
                'wallet_adoption' => $this->metric(
                    $credentialsIssued > 0 ? round(($walletPasses / $credentialsIssued) * 100, 1) : null,
                    $credentialsIssued > 0,
                ),
                'checkins' => $this->metric($checkedInAttendees),
                'first_scan_success_rate' => $this->metric(
                    $firstScanSuccessRate['rate'],
                    $firstScanSuccessRate['available'],
                ),
                'checkin_success_rate' => $this->metric(
                    $scanTotal > 0 ? round(($acceptedScans / $scanTotal) * 100, 1) : null,
                    $scanTotal > 0,
                ),
                'badge_prints' => $this->metric((int) ($badgeByStatus['printed'] ?? 0)),
                'acs_entries_accepted' => $this->metric($acsAccepted),
                'acs_entries_rejected' => $this->metric($acsRejected),
                'orders_by_status' => $ordersByStatus,
                'categories' => $categories,
                'ticket_types' => $ticketTypes,
                'checkins_by_day' => $this->checkinsByDay($tenantId, (string) $eventId, $timezone, $event),
                'checkins_by_hour' => $checkinsByHour,
                'funnel' => $funnel,
                'venue_markers' => $venueMarkers,
                'badge_jobs' => [
                    'by_status' => [
                        'queued' => (int) ($badgeByStatus['queued'] ?? 0),
                        'printed' => (int) ($badgeByStatus['printed'] ?? 0),
                        'failed' => (int) ($badgeByStatus['failed'] ?? 0),
                    ],
                    'reprints' => $badgeReprints,
                ],
                'top_reject_reasons' => $rejectReasons,
                'kiosks' => $kioskCounts,
            ],
        ];
    }

    /** @return array{value: int|float|string|null, available: bool} */
    private function metric(int|float|string|null $value, bool $available = true): array
    {
        return [
            'value' => $value,
            'available' => $available && $value !== null,
        ];
    }

    /**
     * @return array{rate: float|null, available: bool}
     */
    private function firstScanSuccessRate(string $tenantId, string $eventId): array
    {
        $driver = DB::connection()->getDriverName();
        $partition = $driver === 'mysql'
            ? 'ROW_NUMBER() OVER (PARTITION BY attendee_id ORDER BY scanned_at ASC, id ASC)'
            : 'ROW_NUMBER() OVER (PARTITION BY attendee_id ORDER BY scanned_at ASC, id ASC)';

        $sql = "
            SELECT
                COUNT(*) as scanned_attendees,
                SUM(CASE WHEN result IN ('accepted', 'manual_override') THEN 1 ELSE 0 END) as first_success
            FROM (
                SELECT attendee_id, result, {$partition} as rn
                FROM scan_events
                WHERE tenant_id = ?
                  AND event_id = ?
                  AND attendee_id IS NOT NULL
            ) ranked
            WHERE rn = 1
        ";

        try {
            $row = DB::selectOne($sql, [$tenantId, $eventId]);
        } catch (\Throwable) {
            return ['rate' => null, 'available' => false];
        }

        $scanned = (int) ($row->scanned_attendees ?? 0);
        if ($scanned === 0) {
            return ['rate' => null, 'available' => false];
        }

        $success = (int) ($row->first_success ?? 0);

        return [
            'rate' => round(($success / $scanned) * 100, 1),
            'available' => true,
        ];
    }

    /**
     * @return list<array{id: string|null, name: string, attendees: int, checked_in: int}>
     */
    private function categoryBreakdown(string $tenantId, string $eventId): array
    {
        $rows = Attendee::query()
            ->from('attendees')
            ->leftJoin('orders', function ($join) use ($tenantId, $eventId): void {
                $join->on('orders.id', '=', 'attendees.order_id')
                    ->where('orders.tenant_id', '=', $tenantId)
                    ->where('orders.event_id', '=', $eventId);
            })
            ->where('attendees.tenant_id', $tenantId)
            ->where('attendees.event_id', $eventId)
            ->selectRaw("
                orders.event_category_id as category_id,
                COUNT(attendees.id) as attendees_count,
                SUM(CASE
                    WHEN attendees.checkin_status = 'checked_in' OR attendees.first_checked_in_at IS NOT NULL THEN 1
                    ELSE 0
                END) as checked_in_count
            ")
            ->groupBy('orders.event_category_id')
            ->get();

        $categoryIds = $rows->pluck('category_id')->filter()->unique()->values()->all();
        $categories = $categoryIds === []
            ? collect()
            : EventCategory::query()
                ->where('event_id', $eventId)
                ->whereIn('id', $categoryIds)
                ->get()
                ->keyBy('id');

        $revenueByCategory = Order::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('status', 'paid')
            ->selectRaw('event_category_id as category_id, COALESCE(SUM(total_minor), 0) as revenue_minor')
            ->groupBy('event_category_id')
            ->pluck('revenue_minor', 'category_id');

        return $rows->map(function ($row) use ($categories, $revenueByCategory): array {
            $id = $row->category_id !== null ? (string) $row->category_id : null;
            $category = $id !== null ? $categories->get($id) : null;
            $attendees = (int) $row->attendees_count;
            $checkedIn = (int) $row->checked_in_count;

            return [
                'id' => $id,
                'name' => $category
                    ? (string) ($category->name ?: $category->name_ar ?: ('#'.$id))
                    : 'Unassigned',
                'name_ar' => $category ? (string) ($category->name_ar ?: $category->name ?: '') : 'غير معيّن',
                'attendees' => $attendees,
                'checked_in' => $checkedIn,
                'checkin_rate' => $attendees > 0 ? round(($checkedIn / $attendees) * 100, 1) : null,
                'revenue_minor' => (int) ($revenueByCategory[$row->category_id] ?? 0),
            ];
        })->values()->all();
    }

    /**
     * @return list<array{id: string|null, name: string, attendees: int, checked_in: int}>
     */
    private function ticketTypeBreakdown(string $tenantId, string $eventId): array
    {
        $rows = Attendee::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->selectRaw("
                ticket_type_id,
                COUNT(*) as attendees_count,
                SUM(CASE
                    WHEN checkin_status = 'checked_in' OR first_checked_in_at IS NOT NULL THEN 1
                    ELSE 0
                END) as checked_in_count
            ")
            ->groupBy('ticket_type_id')
            ->get();

        $ticketIds = $rows->pluck('ticket_type_id')->filter()->unique()->values()->all();
        $tickets = $ticketIds === []
            ? collect()
            : TicketType::query()
                ->where('tenant_id', $tenantId)
                ->where('event_id', $eventId)
                ->whereIn('id', $ticketIds)
                ->get()
                ->keyBy('id');

        $revenueByTicket = collect();
        try {
            $revenueByTicket = DB::table('order_items')
                ->join('orders', function ($join) use ($tenantId, $eventId): void {
                    $join->on('orders.id', '=', 'order_items.order_id')
                        ->where('orders.tenant_id', '=', $tenantId)
                        ->where('orders.event_id', '=', $eventId)
                        ->where('orders.status', '=', 'paid');
                })
                ->where('order_items.tenant_id', $tenantId)
                ->where('order_items.event_id', $eventId)
                ->selectRaw('order_items.ticket_type_id, COALESCE(SUM(order_items.total_minor), 0) as revenue_minor')
                ->groupBy('order_items.ticket_type_id')
                ->pluck('revenue_minor', 'ticket_type_id');
        } catch (\Throwable) {
            $revenueByTicket = collect();
        }

        return $rows->map(function ($row) use ($tickets, $revenueByTicket): array {
            $id = $row->ticket_type_id !== null ? (string) $row->ticket_type_id : null;
            $ticket = $id !== null ? $tickets->get($id) : null;
            $attendees = (int) $row->attendees_count;
            $checkedIn = (int) $row->checked_in_count;

            return [
                'id' => $id,
                'name' => $ticket
                    ? (string) ($ticket->name_en ?: $ticket->name_ar ?: $ticket->code)
                    : 'Unassigned',
                'name_ar' => $ticket
                    ? (string) ($ticket->name_ar ?: $ticket->name_en ?: $ticket->code)
                    : 'غير معيّن',
                'attendees' => $attendees,
                'checked_in' => $checkedIn,
                'checkin_rate' => $attendees > 0 ? round(($checkedIn / $attendees) * 100, 1) : null,
                'revenue_minor' => (int) ($revenueByTicket[$row->ticket_type_id] ?? 0),
            ];
        })->values()->all();
    }

    /**
     * @return list<array{date: string, accepted_scans: int, unique_attendees: int}>
     */
    private function checkinsByDay(string $tenantId, string $eventId, string $timezone, Event $event): array
    {
        $end = CarbonImmutable::now($timezone)->endOfDay();
        $start = $end->subDays(13)->startOfDay();

        if ($event->start_at !== null) {
            $eventStart = EventWallClockDateTime::asEventLocal($event->start_at, $timezone)?->startOfDay();
            if ($eventStart !== null && $eventStart->greaterThan($start) && $eventStart->lessThanOrEqualTo($end)) {
                $start = $eventStart;
            }
        }

        $driver = DB::connection()->getDriverName();

        try {
            if ($driver === 'sqlite') {
                $rows = DB::select("
                    SELECT
                        strftime('%Y-%m-%d', scanned_at) as day,
                        COUNT(*) as accepted_scans,
                        COUNT(DISTINCT attendee_id) as unique_attendees
                    FROM scan_events
                    WHERE tenant_id = ?
                      AND event_id = ?
                      AND result IN ('accepted', 'manual_override')
                      AND scanned_at BETWEEN ? AND ?
                    GROUP BY day
                    ORDER BY day ASC
                ", [$tenantId, $eventId, $start->utc()->toDateTimeString(), $end->utc()->toDateTimeString()]);
            } else {
                $rows = DB::select("
                    SELECT
                        DATE(CONVERT_TZ(scanned_at, '+00:00', ?)) as day,
                        COUNT(*) as accepted_scans,
                        COUNT(DISTINCT attendee_id) as unique_attendees
                    FROM scan_events
                    WHERE tenant_id = ?
                      AND event_id = ?
                      AND result IN ('accepted', 'manual_override')
                      AND scanned_at BETWEEN ? AND ?
                    GROUP BY day
                    ORDER BY day ASC
                ", [
                    $timezone,
                    $tenantId,
                    $eventId,
                    $start->utc()->toDateTimeString(),
                    $end->utc()->toDateTimeString(),
                ]);
            }
        } catch (\Throwable) {
            // Fallback without timezone conversion (MySQL without time zone tables, etc.)
            $rows = DB::select("
                SELECT
                    DATE(scanned_at) as day,
                    COUNT(*) as accepted_scans,
                    COUNT(DISTINCT attendee_id) as unique_attendees
                FROM scan_events
                WHERE tenant_id = ?
                  AND event_id = ?
                  AND result IN ('accepted', 'manual_override')
                  AND scanned_at BETWEEN ? AND ?
                GROUP BY day
                ORDER BY day ASC
            ", [$tenantId, $eventId, $start->utc()->toDateTimeString(), $end->utc()->toDateTimeString()]);
        }

        $byDay = collect($rows)->keyBy(fn ($row): string => (string) $row->day);
        $days = [];
        for ($cursor = $start; $cursor->lessThanOrEqualTo($end); $cursor = $cursor->addDay()) {
            $key = $cursor->toDateString();
            $row = $byDay->get($key);
            $days[] = [
                'date' => $key,
                'accepted_scans' => (int) ($row->accepted_scans ?? 0),
                'unique_attendees' => (int) ($row->unique_attendees ?? 0),
            ];
        }

        return $days;
    }

    /**
     * @return list<array{reason: string, count: int, percent: float|null}>
     */
    private function topRejectReasons(string $tenantId, string $eventId, int $rejectedTotal = 0): array
    {
        return ScanEvent::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('result', 'rejected')
            ->whereNotNull('reason')
            ->where('reason', '!=', '')
            ->selectRaw('reason, COUNT(*) as aggregate_count')
            ->groupBy('reason')
            ->orderByDesc('aggregate_count')
            ->limit(8)
            ->get()
            ->map(fn ($row): array => [
                'reason' => (string) $row->reason,
                'count' => (int) $row->aggregate_count,
                'percent' => $rejectedTotal > 0
                    ? round(((int) $row->aggregate_count / $rejectedTotal) * 100, 1)
                    : null,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{key: string, count: int, conversion_from_previous: float|null}>
     */
    private function funnel(
        Event $event,
        string $tenantId,
        string $eventId,
        int $registrations,
        int $paidOrders,
        int $credentialsIssued,
        int $checkedIn,
    ): array {
        $steps = [];

        $isPrivate = in_array((string) $event->tier, ['private', 'both'], true);
        if ($isPrivate) {
            $invited = EventRegistrationInvite::query()
                ->where('tenant_id', $tenantId)
                ->where('event_id', $eventId)
                ->count();
            $steps[] = ['key' => 'invited', 'count' => $invited, 'conversion_from_previous' => null];
        }

        $isFree = (string) $event->registration_mode === RegistrationMode::FreeRegistration->value;
        $paidCount = $isFree ? $registrations : $paidOrders;

        $base = [
            ['key' => 'registered', 'count' => $registrations],
            ['key' => 'paid', 'count' => $paidCount],
            ['key' => 'credentialed', 'count' => $credentialsIssued],
            ['key' => 'checked_in', 'count' => $checkedIn],
        ];

        foreach ($base as $step) {
            $previous = $steps === [] ? null : $steps[array_key_last($steps)]['count'];
            $steps[] = [
                'key' => $step['key'],
                'count' => $step['count'],
                'conversion_from_previous' => $previous !== null && $previous > 0
                    ? round(($step['count'] / $previous) * 100, 1)
                    : null,
            ];
        }

        return $steps;
    }

    /**
     * @return list<array{hour: string, accepted_scans: int, unique_attendees: int}>
     */
    private function checkinsByHour(string $tenantId, string $eventId, string $timezone, Event $event): array
    {
        $now = CarbonImmutable::now($timezone);
        if ($event->start_at !== null && $event->end_at !== null) {
            $start = EventWallClockDateTime::asEventLocal($event->start_at, $timezone)?->startOfDay();
            $end = EventWallClockDateTime::asEventLocal($event->end_at, $timezone)?->endOfDay();
            if ($start === null || $end === null) {
                $end = $now->endOfHour();
                $start = $end->subHours(23)->startOfHour();
            } elseif ($start->diffInHours($end) > 72) {
                $end = $start->addHours(72)->endOfHour();
            }
        } else {
            $end = $now->endOfHour();
            $start = $end->subHours(23)->startOfHour();
        }

        $driver = DB::connection()->getDriverName();

        try {
            if ($driver === 'sqlite') {
                $rows = DB::select("
                    SELECT
                        strftime('%Y-%m-%d %H:00:00', scanned_at) as hour_bucket,
                        COUNT(*) as accepted_scans,
                        COUNT(DISTINCT attendee_id) as unique_attendees
                    FROM scan_events
                    WHERE tenant_id = ?
                      AND event_id = ?
                      AND result IN ('accepted', 'manual_override')
                      AND scanned_at BETWEEN ? AND ?
                    GROUP BY hour_bucket
                    ORDER BY hour_bucket ASC
                ", [$tenantId, $eventId, $start->utc()->toDateTimeString(), $end->utc()->toDateTimeString()]);
            } else {
                $rows = DB::select("
                    SELECT
                        DATE_FORMAT(CONVERT_TZ(scanned_at, '+00:00', ?), '%Y-%m-%d %H:00:00') as hour_bucket,
                        COUNT(*) as accepted_scans,
                        COUNT(DISTINCT attendee_id) as unique_attendees
                    FROM scan_events
                    WHERE tenant_id = ?
                      AND event_id = ?
                      AND result IN ('accepted', 'manual_override')
                      AND scanned_at BETWEEN ? AND ?
                    GROUP BY hour_bucket
                    ORDER BY hour_bucket ASC
                ", [
                    $timezone,
                    $tenantId,
                    $eventId,
                    $start->utc()->toDateTimeString(),
                    $end->utc()->toDateTimeString(),
                ]);
            }
        } catch (\Throwable) {
            $rows = DB::select("
                SELECT
                    DATE_FORMAT(scanned_at, '%Y-%m-%d %H:00:00') as hour_bucket,
                    COUNT(*) as accepted_scans,
                    COUNT(DISTINCT attendee_id) as unique_attendees
                FROM scan_events
                WHERE tenant_id = ?
                  AND event_id = ?
                  AND result IN ('accepted', 'manual_override')
                  AND scanned_at BETWEEN ? AND ?
                GROUP BY hour_bucket
                ORDER BY hour_bucket ASC
            ", [$tenantId, $eventId, $start->utc()->toDateTimeString(), $end->utc()->toDateTimeString()]);
        }

        $byHour = collect($rows)->keyBy(fn ($row): string => (string) $row->hour_bucket);
        $hours = [];
        for ($cursor = $start->startOfHour(); $cursor->lessThanOrEqualTo($end); $cursor = $cursor->addHour()) {
            $key = $cursor->format('Y-m-d H:00:00');
            $row = $byHour->get($key);
            $hours[] = [
                'hour' => $key,
                'accepted_scans' => (int) ($row->accepted_scans ?? 0),
                'unique_attendees' => (int) ($row->unique_attendees ?? 0),
            ];
        }

        return $hours;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function venueMarkers(string $tenantId, string $eventId): array
    {
        $event = Event::query()->find($eventId);
        $timezone = (string) ($event?->timezone ?? 'UTC');

        $venues = EventVenue::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderBy('sort_order')
            ->get();

        if ($venues->isEmpty()) {
            return [];
        }

        $stats = Attendee::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->selectRaw("
                event_venue_id,
                COUNT(*) as registered,
                SUM(CASE
                    WHEN checkin_status = 'checked_in' OR first_checked_in_at IS NOT NULL THEN 1
                    ELSE 0
                END) as checked_in
            ")
            ->groupBy('event_venue_id')
            ->get()
            ->keyBy(fn ($row) => $row->event_venue_id !== null ? (string) $row->event_venue_id : 'null');

        $palette = [
            '#0ea5e9', '#8b5cf6', '#10b981', '#f59e0b', '#ef4444',
            '#06b6d4', '#ec4899', '#84cc16', '#f97316', '#6366f1',
        ];

        return $venues->values()->map(function (EventVenue $venue, int $index) use ($stats, $palette, $timezone): array {
            $id = (string) $venue->id;
            $row = $stats->get($id);
            $registered = (int) ($row->registered ?? 0);
            $checkedIn = (int) ($row->checked_in ?? 0);

            return [
                'venue_id' => $id,
                'venue_name' => [
                    'en' => (string) ($venue->name_en ?: $venue->name_ar ?: ''),
                    'ar' => (string) ($venue->name_ar ?: $venue->name_en ?: ''),
                ],
                'latitude' => (float) $venue->latitude,
                'longitude' => (float) $venue->longitude,
                'start_at' => EventWallClockDateTime::toIso8601($venue->start_at, $timezone),
                'end_at' => EventWallClockDateTime::toIso8601($venue->end_at, $timezone),
                'timezone' => $timezone,
                'address' => $venue->location_address ? (string) $venue->location_address : null,
                'registered' => $registered,
                'checked_in' => $checkedIn,
                'checkin_rate' => $registered > 0 ? round(($checkedIn / $registered) * 100, 1) : null,
                'color' => $palette[$index % count($palette)],
            ];
        })->all();
    }

    /**
     * @return array{total: int, online: int, offline: int, degraded: int, retired: int, registered: int}
     */
    private function kioskCounts(string $tenantId, string $eventId): array
    {
        static $cache = [];
        $cacheKey = $tenantId.':'.$eventId;
        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        $threshold = EventCheckInSetting::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->value('kiosk_offline_threshold_seconds');
        $threshold = is_numeric($threshold)
            ? (int) $threshold
            : (int) config('printing.kiosk.default_offline_threshold_seconds', 120);

        $counts = [
            'total' => 0,
            'online' => 0,
            'offline' => 0,
            'degraded' => 0,
            'retired' => 0,
            'registered' => 0,
        ];

        $now = $this->clock->now();
        Kiosk::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->get()
            ->each(function (Kiosk $kiosk) use (&$counts, $threshold, $now): void {
                $counts['total']++;
                if ($kiosk->status === 'registered' && $kiosk->last_heartbeat_at === null) {
                    $counts['registered']++;
                }
                $status = $this->kioskStatus->derive($kiosk, $threshold, $now);
                $counts[$status] = ($counts[$status] ?? 0) + 1;
            });

        return $cache[$cacheKey] = $counts;
    }
}
