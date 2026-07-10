<?php

namespace App\Modules\Scanning\Application\Jobs;

use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Scanning\Infrastructure\Persistence\Models\EventCheckInSummary;
use App\Modules\Scanning\Infrastructure\Persistence\Models\ScanEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class RefreshEventCheckInSummaryJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $eventId,
    ) {}

    public function handle(): void
    {
        Event::query()
            ->where('tenant_id', $this->tenantId)
            ->where('id', $this->eventId)
            ->firstOrFail();

        $registeredCount = Attendee::query()
            ->where('tenant_id', $this->tenantId)
            ->where('event_id', $this->eventId)
            ->count();

        $checkedInCount = ScanEvent::query()
            ->where('tenant_id', $this->tenantId)
            ->where('event_id', $this->eventId)
            ->whereIn('result', ['accepted', 'manual_override'])
            ->count();

        $rejectedCount = ScanEvent::query()
            ->where('tenant_id', $this->tenantId)
            ->where('event_id', $this->eventId)
            ->where('result', 'rejected')
            ->count();

        $duplicateCount = ScanEvent::query()
            ->where('tenant_id', $this->tenantId)
            ->where('event_id', $this->eventId)
            ->whereIn('result', ['duplicate', 'revoked', 'expired'])
            ->count();

        $lastScanAt = ScanEvent::query()
            ->where('tenant_id', $this->tenantId)
            ->where('event_id', $this->eventId)
            ->max('scanned_at');

        EventCheckInSummary::query()->updateOrCreate(
            ['tenant_id' => $this->tenantId, 'event_id' => $this->eventId],
            [
                'registered_count' => $registeredCount,
                'checked_in_count' => $checkedInCount,
                'rejected_count' => $rejectedCount,
                'duplicate_count' => $duplicateCount,
                'last_scan_at' => $lastScanAt,
            ],
        );
    }
}
