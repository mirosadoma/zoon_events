<?php

namespace App\Modules\Scanning\Application\Queries;

use App\Modules\Scanning\Infrastructure\Persistence\Models\EventCheckInSummary;

final readonly class GetCheckInSummaryQuery
{
    public function handle(string $tenantId, string $eventId): EventCheckInSummary
    {
        $summary = EventCheckInSummary::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->first();

        if ($summary !== null) {
            return $summary;
        }

        return EventCheckInSummary::query()->create([
            'tenant_id' => $tenantId,
            'event_id' => $eventId,
            'registered_count' => 0,
            'checked_in_count' => 0,
            'rejected_count' => 0,
            'duplicate_count' => 0,
            'last_scan_at' => null,
        ]);
    }
}
