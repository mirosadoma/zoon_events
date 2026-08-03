<?php

namespace App\Modules\AccessControl\Application\Actions;

use App\Modules\AccessControl\Domain\Events\AcsZoneDeleted;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsAuthorizationRule;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsLane;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsZone;
use App\Modules\Audit\Application\AuditedTransaction;
use App\Modules\Shared\Http\Problems\Phase4Problem;

final readonly class DeleteAcsZoneAction
{
    public function __construct(private AuditedTransaction $audited) {}

    public function execute(AcsZone $zone): void
    {
        $hasLanes = AcsLane::query()
            ->where('tenant_id', $zone->tenant_id)
            ->where('event_id', $zone->event_id)
            ->where('zone_id', $zone->id)
            ->exists();

        $hasRules = AcsAuthorizationRule::query()
            ->where('tenant_id', $zone->tenant_id)
            ->where('event_id', $zone->event_id)
            ->where('zone_id', $zone->id)
            ->exists();

        if ($hasLanes || $hasRules) {
            throw Phase4Problem::make('acs_zone_in_use');
        }

        $tenantId = (string) $zone->tenant_id;
        $eventId = (string) $zone->event_id;
        $zoneId = (string) $zone->id;

        $this->audited->run(
            function () use ($zone): bool {
                return (bool) $zone->delete();
            },
            fn (): mixed => event(new AcsZoneDeleted($tenantId, $eventId, $zoneId)),
        );
    }
}
