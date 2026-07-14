<?php

namespace App\Modules\AdminConsole\ViewModels\Kiosk;

use App\Modules\BadgePrinting\Infrastructure\Persistence\Models\BadgePrintJob;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Kiosk\Domain\KioskStatusDeriver;
use App\Modules\Kiosk\Infrastructure\Persistence\Models\Kiosk;
use App\Modules\Scanning\Infrastructure\Persistence\Models\EventCheckInSetting;
use App\Modules\Scanning\Infrastructure\Persistence\Models\ScanEvent;
use App\Modules\Shared\Contracts\Clock;
use Illuminate\Support\Collection;

final readonly class KioskViewModel
{
    public function __construct(
        private KioskStatusDeriver $deriver,
        private Clock $clock,
    ) {}

    /**
     * @param  Collection<int, Kiosk>  $kiosks
     * @param  array{page: int, per_page: int, total: int, last_page: int}  $pagination
     * @return array{
     *     event: array<string, mixed>,
     *     tenantId: string,
     *     kiosks: list<array<string, mixed>>,
     *     pagination: array{page: int, per_page: int, total: int, last_page: int}
     * }
     */
    public function index(
        Event $event,
        string $tenantId,
        Collection $kiosks,
        int $threshold,
        array $pagination = ['page' => 1, 'per_page' => 15, 'total' => 0, 'last_page' => 1],
    ): array {
        return [
            'event' => $this->eventRow($event),
            'tenantId' => $tenantId,
            'kiosks' => $kiosks
                ->map(fn (Kiosk $kiosk): array => $this->kioskRow($kiosk, $threshold))
                ->values()
                ->all(),
            'pagination' => [
                'page' => (int) $pagination['page'],
                'per_page' => (int) $pagination['per_page'],
                'total' => (int) $pagination['total'],
                'last_page' => (int) $pagination['last_page'],
            ],
        ];
    }

    /**
     * @return array{event: array<string, mixed>, tenantId: string, kiosk: array<string, mixed>}
     */
    public function detail(
        Event $event,
        string $tenantId,
        Kiosk $kiosk,
        int $threshold,
        Collection $recentCheckins,
        Collection $recentPrintJobs,
    ): array {
        return [
            'event' => $this->eventRow($event),
            'tenantId' => $tenantId,
            'kiosk' => [
                ...$this->kioskRow($kiosk, $threshold),
                'location_label' => $kiosk->location_label,
                'recent_checkins' => $recentCheckins
                    ->map(fn (ScanEvent $scan): array => [
                        'id' => (string) $scan->id,
                        'result' => $scan->result instanceof \BackedEnum ? $scan->result->value : (string) $scan->result,
                        'reason' => $scan->reason,
                        'scanned_at' => $scan->scanned_at?->toIso8601String(),
                    ])
                    ->values()
                    ->all(),
                'recent_print_jobs' => $recentPrintJobs
                    ->map(fn (BadgePrintJob $job): array => [
                        'id' => (string) $job->id,
                        'status' => $job->status instanceof \BackedEnum ? $job->status->value : (string) $job->status,
                        'is_reprint' => (bool) $job->is_reprint,
                        'reprint_reason' => $job->reprint_reason,
                        'printed_at' => $job->printed_at?->toIso8601String(),
                    ])
                    ->values()
                    ->all(),
            ],
        ];
    }

    public function offlineThreshold(string $tenantId, string $eventId): int
    {
        $settings = EventCheckInSetting::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->first();

        return $settings?->kiosk_offline_threshold_seconds
            ?? (int) config('printing.kiosk.default_offline_threshold_seconds', 120);
    }

    /** @return array<string, mixed> */
    private function eventRow(Event $event): array
    {
        return [
            'id' => $event->id,
            'name' => ['en' => $event->name_en, 'ar' => $event->name_ar],
        ];
    }

    /** @return array<string, mixed> */
    private function kioskRow(Kiosk $kiosk, int $threshold): array
    {
        return [
            'id' => (string) $kiosk->id,
            'device_name' => $kiosk->device_name,
            'device_code' => $kiosk->device_code,
            'status' => $this->deriver->derive($kiosk, $threshold, $this->clock->now()),
            'printer_status' => $kiosk->printer_status instanceof \BackedEnum
                ? $kiosk->printer_status->value
                : (string) $kiosk->printer_status,
            'last_heartbeat_at' => $kiosk->last_heartbeat_at?->toIso8601String(),
            'confirmation_required' => (bool) $kiosk->confirmation_required,
        ];
    }
}
