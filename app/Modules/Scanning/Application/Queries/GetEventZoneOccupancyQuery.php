<?php

namespace App\Modules\Scanning\Application\Queries;

use App\Modules\Events\Infrastructure\Persistence\Models\EventZone;
use App\Modules\Scanning\Infrastructure\Persistence\Models\ScanEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class GetEventZoneOccupancyQuery
{
    /**
     * @return array{
     *     zones: list<array<string, mixed>>,
     *     untracked_event_zone_ids: list<string>,
     *     totals: array{inside: int, capacity: int|null, tracked_zones: int},
     *     generated_at: string
     * }
     */
    public function summary(string $tenantId, string $eventId): array
    {
        $zones = EventZone::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->orderBy('zone_name_en')
            ->get();

        $latestPerAttendee = ScanEvent::query()
            ->select('attendee_id', DB::raw('MAX(scanned_at) as latest_at'))
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->whereNotNull('zone_id')
            ->whereNotNull('attendee_id')
            ->whereIn('result', ['accepted', 'manual_override'])
            ->groupBy('attendee_id');

        $currentCounts = ScanEvent::query()
            ->fromSub($latestPerAttendee, 'latest')
            ->join('scan_events as se', function ($join) use ($tenantId, $eventId): void {
                $join->on('se.attendee_id', '=', 'latest.attendee_id')
                    ->on('se.scanned_at', '=', 'latest.latest_at')
                    ->where('se.tenant_id', $tenantId)
                    ->where('se.event_id', $eventId)
                    ->whereNotNull('se.zone_id')
                    ->whereIn('se.result', ['accepted', 'manual_override']);
            })
            ->select('se.zone_id', DB::raw('COUNT(DISTINCT se.attendee_id) as inside_count'), DB::raw('MAX(se.scanned_at) as last_scan_at'))
            ->groupBy('se.zone_id')
            ->get()
            ->keyBy(fn ($row) => (string) $row->zone_id);

        $rows = [];
        $totalInside = 0;
        $totalCapacity = 0;
        $hasCapacity = false;

        foreach ($zones as $zone) {
            $zoneId = (string) $zone->id;
            $countRow = $currentCounts->get($zoneId);
            $inside = (int) ($countRow->inside_count ?? 0);
            $capacity = $zone->capacity !== null ? (int) $zone->capacity : null;
            $utilization = ($capacity !== null && $capacity > 0) ? round($inside / $capacity, 4) : null;
            $level = $this->level($utilization, $inside);

            $totalInside += $inside;
            if ($capacity !== null) {
                $hasCapacity = true;
                $totalCapacity += $capacity;
            }

            $rows[] = [
                'event_zone_id' => $zoneId,
                'scanner_code' => $zone->scanner_code !== null ? (string) $zone->scanner_code : null,
                'name' => ['en' => (string) $zone->zone_name_en, 'ar' => (string) $zone->zone_name_ar],
                'inside_count' => $inside,
                'capacity' => $capacity,
                'utilization' => $utilization,
                'level' => $level,
                'coverage' => 'tracked',
                'last_scan_at' => isset($countRow?->last_scan_at)
                    ? Carbon::parse($countRow->last_scan_at)->toIso8601String()
                    : null,
            ];
        }

        return [
            'zones' => $rows,
            'untracked_event_zone_ids' => [],
            'totals' => [
                'inside' => $totalInside,
                'capacity' => $hasCapacity ? $totalCapacity : null,
                'tracked_zones' => count($rows),
            ],
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array{
     *     range: string,
     *     hourly: list<array{hour: string, zone_id: string, entries: int}>,
     *     peaks: list<array{event_zone_id: string, peak_inside: int}>,
     *     generated_at: string
     * }
     */
    public function analytics(string $tenantId, string $eventId): array
    {
        $start = now()->startOfDay();
        $end = now()->endOfDay();

        $hourly = ScanEvent::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->whereNotNull('zone_id')
            ->whereIn('result', ['accepted', 'manual_override'])
            ->whereBetween('scanned_at', [$start, $end])
            ->selectRaw("DATE_FORMAT(scanned_at, '%Y-%m-%d %H:00:00') as hour_bucket, zone_id, COUNT(*) as entries")
            ->groupBy('hour_bucket', 'zone_id')
            ->orderBy('hour_bucket')
            ->get()
            ->map(fn ($row): array => [
                'hour' => Carbon::parse($row->hour_bucket)->toIso8601String(),
                'zone_id' => (string) $row->zone_id,
                'entries' => (int) $row->entries,
            ])
            ->all();

        // Approximate peaks by replaying today's zone-scoped accepted scans in order.
        $events = ScanEvent::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->whereNotNull('zone_id')
            ->whereNotNull('attendee_id')
            ->whereIn('result', ['accepted', 'manual_override'])
            ->whereBetween('scanned_at', [$start, $end])
            ->orderBy('scanned_at')
            ->orderBy('id')
            ->get(['attendee_id', 'zone_id']);

        $currentZone = [];
        $zoneCounts = [];
        $peakCounts = [];
        foreach ($events as $event) {
            $attendeeId = (string) $event->attendee_id;
            $zoneId = (string) $event->zone_id;
            $prev = $currentZone[$attendeeId] ?? null;
            if ($prev !== null && $prev !== $zoneId) {
                $zoneCounts[$prev] = max(0, ($zoneCounts[$prev] ?? 0) - 1);
            }
            if ($prev !== $zoneId) {
                $currentZone[$attendeeId] = $zoneId;
                $zoneCounts[$zoneId] = ($zoneCounts[$zoneId] ?? 0) + 1;
                $peakCounts[$zoneId] = max($peakCounts[$zoneId] ?? 0, $zoneCounts[$zoneId]);
            }
        }

        $peakRows = [];
        foreach ($peakCounts as $zoneId => $peak) {
            $peakRows[] = [
                'event_zone_id' => (string) $zoneId,
                'peak_inside' => (int) $peak,
            ];
        }

        return [
            'range' => 'today',
            'hourly' => $hourly,
            'peaks' => $peakRows,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    private function level(?float $utilization, int $inside): string
    {
        if ($utilization === null) {
            return $inside > 0 ? 'tracked' : 'empty';
        }

        return match (true) {
            $utilization >= 0.9 => 'critical',
            $utilization >= 0.75 => 'high',
            $utilization >= 0.5 => 'medium',
            $utilization >= 0.25 => 'low',
            default => 'empty',
        };
    }
}
