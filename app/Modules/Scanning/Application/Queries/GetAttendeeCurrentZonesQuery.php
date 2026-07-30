<?php

namespace App\Modules\Scanning\Application\Queries;

use App\Modules\Events\Infrastructure\Persistence\Models\EventZone;
use App\Modules\Scanning\Infrastructure\Persistence\Models\ScanEvent;
use Illuminate\Support\Facades\DB;

final class GetAttendeeCurrentZonesQuery
{
    /**
     * Latest accepted/manual_override zone-scoped scan per attendee.
     *
     * @param  list<int|string>  $attendeeIds
     * @return array<string, array{id: string, name: array{en: string, ar: string}}>
     */
    public function forAttendees(string $tenantId, string $eventId, array $attendeeIds): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map(static fn ($id): string => (string) $id, $attendeeIds),
            static fn (string $id): bool => $id !== '',
        )));

        if ($ids === []) {
            return [];
        }

        $latestPerAttendee = ScanEvent::query()
            ->select('attendee_id', DB::raw('MAX(scanned_at) as latest_at'))
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->whereIn('attendee_id', $ids)
            ->whereNotNull('zone_id')
            ->whereIn('result', ['accepted', 'manual_override'])
            ->groupBy('attendee_id');

        $rows = ScanEvent::query()
            ->fromSub($latestPerAttendee, 'latest')
            ->join('scan_events as se', function ($join) use ($tenantId, $eventId): void {
                $join->on('se.attendee_id', '=', 'latest.attendee_id')
                    ->on('se.scanned_at', '=', 'latest.latest_at')
                    ->where('se.tenant_id', $tenantId)
                    ->where('se.event_id', $eventId)
                    ->whereNotNull('se.zone_id')
                    ->whereIn('se.result', ['accepted', 'manual_override']);
            })
            ->select('se.attendee_id', 'se.zone_id')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $zoneIds = $rows->pluck('zone_id')->map(fn ($id): string => (string) $id)->unique()->values()->all();
        $zones = EventZone::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->whereIn('id', $zoneIds)
            ->get()
            ->keyBy(fn (EventZone $zone): string => (string) $zone->id);

        $map = [];
        foreach ($rows as $row) {
            $attendeeId = (string) $row->attendee_id;
            $zoneId = (string) $row->zone_id;
            $zone = $zones->get($zoneId);
            if ($zone === null) {
                continue;
            }

            $map[$attendeeId] = [
                'id' => $zoneId,
                'name' => [
                    'en' => (string) $zone->zone_name_en,
                    'ar' => (string) $zone->zone_name_ar,
                ],
            ];
        }

        return $map;
    }
}
