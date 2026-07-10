<?php

namespace App\Modules\Scanning\Domain;

use App\Modules\Scanning\Infrastructure\Persistence\Models\EventCheckInSetting;
use App\Modules\Scanning\Infrastructure\Persistence\Models\ScanEvent;
use Illuminate\Support\Facades\DB;

final readonly class SingleEntryEvaluator
{
    public function isDuplicate(string $tenantId, string $eventId, string $credentialId, ?string $ticketTypeId): bool
    {
        $settings = EventCheckInSetting::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->first();

        $enabled = $settings?->single_entry_enabled ?? true;
        $scope = $settings?->single_entry_scope ?? 'event';

        if (! $enabled) {
            return false;
        }

        $query = ScanEvent::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('credential_id', $credentialId)
            ->whereIn('result', ['accepted', 'manual_override']);

        if ($scope === 'ticket_type' && $ticketTypeId !== null) {
            $query->whereExists(function ($sub) use ($tenantId, $eventId, $ticketTypeId): void {
                $sub->select(DB::raw('1'))
                    ->from('credentials')
                    ->whereColumn('credentials.id', 'scan_events.credential_id')
                    ->where('credentials.tenant_id', $tenantId)
                    ->where('credentials.event_id', $eventId)
                    ->where('credentials.ticket_type_id', $ticketTypeId);
            });
        }

        return $query->exists();
    }
}
