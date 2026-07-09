<?php

namespace App\Modules\AdminConsole\ViewModels\CheckIn;

use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Scanning\Infrastructure\Persistence\Models\ScanEvent;
use Illuminate\Support\Collection;

final readonly class ScanEventsViewModel
{
    /**
     * @param  Collection<int, ScanEvent>  $events
     * @param  array<string, string>  $laneNames
     * @param  array<string, string>  $zoneNames
     * @return array{event: array<string, mixed>, scanEvents: list<array<string, mixed>>}
     */
    public function index(
        Event $event,
        Collection $events,
        array $laneNames = [],
        array $zoneNames = [],
    ): array {
        return [
            'event' => [
                'id' => $event->id,
                'name' => ['en' => $event->name_en, 'ar' => $event->name_ar],
            ],
            'scanEvents' => $events->map(fn (ScanEvent $scan): array => [
                'id' => $scan->id,
                'result' => $scan->result,
                'scanner_type' => $scan->scanner_type,
                'gate_id' => $scan->gate_id,
                'gate_name' => $scan->gate_id !== null ? ($laneNames[$scan->gate_id] ?? null) : null,
                'zone_id' => $scan->zone_id,
                'zone_name' => $scan->zone_id !== null ? ($zoneNames[$scan->zone_id] ?? null) : null,
                'offline' => $scan->offline_mode,
                'attendee_id' => $scan->attendee_id,
                'reason' => $scan->reason,
                'scanned_at' => $scan->scanned_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }
}
