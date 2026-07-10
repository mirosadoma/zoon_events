<?php

namespace App\Modules\AccessControl\Application\Support;

use App\Modules\AccessControl\Infrastructure\Persistence\Models\EmergencyEvent;

final class EmergencyStateService
{
    public function isActiveForZone(string $tenantId, string $eventId, ?string $zoneId): bool
    {
        return EmergencyEvent::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->whereNull('cleared_at')
            ->where(function ($query) use ($zoneId): void {
                $query->whereNull('zone_id');

                if ($zoneId !== null) {
                    $query->orWhere('zone_id', $zoneId);
                }
            })
            ->exists();
    }

    public function hasActiveEmergency(string $tenantId, string $eventId): bool
    {
        return EmergencyEvent::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->whereNull('cleared_at')
            ->exists();
    }
}
