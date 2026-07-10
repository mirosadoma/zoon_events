<?php

namespace App\Modules\AdminConsole\ViewModels\CheckIn;

use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Scanning\Infrastructure\Persistence\Models\EventCheckInSummary;

final readonly class CheckInDashboardViewModel
{
    /**
     * @return array{event: array<string, mixed>, tenantId: string, initialSummary: ?array<string, mixed>}
     */
    public function make(Event $event, string $tenantId, ?EventCheckInSummary $summary = null): array
    {
        return [
            'event' => $this->eventRow($event),
            'tenantId' => $tenantId,
            'initialSummary' => $summary !== null ? $this->summaryRow($summary) : null,
        ];
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
    private function summaryRow(EventCheckInSummary $summary): array
    {
        return [
            'registered_count' => $summary->registered_count,
            'checked_in_count' => $summary->checked_in_count,
            'rejected_count' => $summary->rejected_count,
            'duplicate_count' => $summary->duplicate_count,
            'last_scan_at' => $summary->last_scan_at?->toIso8601String(),
        ];
    }
}
