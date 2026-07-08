<?php

namespace App\Modules\AccessControl\Application\Actions;

use App\Modules\AccessControl\Domain\Events\AcsZoneCreated;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsZone;
use App\Modules\Audit\Application\AuditedTransaction;
use App\Modules\Shared\Http\Problems\Phase4Problem;

final readonly class CreateAcsZoneAction
{
    public function __construct(private AuditedTransaction $audited) {}

    /**
     * @param  array{
     *     name: string,
     *     external_acs_zone_id: string,
     *     anti_passback_enabled?: bool,
     *     unavailability_mode?: string,
     *     emergency_egress_mode?: string,
     *     status?: string,
     * }  $data
     */
    public function execute(string $tenantId, string $eventId, array $data): AcsZone
    {
        if (AcsZone::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('external_acs_zone_id', $data['external_acs_zone_id'])
            ->exists()) {
            throw Phase4Problem::make('acs_duplicate_external_id');
        }

        return $this->audited->run(
            fn (): AcsZone => AcsZone::query()->create([
                'tenant_id' => $tenantId,
                'event_id' => $eventId,
                'name' => $data['name'],
                'external_acs_zone_id' => $data['external_acs_zone_id'],
                'anti_passback_enabled' => $data['anti_passback_enabled'] ?? false,
                'unavailability_mode' => $data['unavailability_mode'] ?? 'fail_closed',
                'emergency_egress_mode' => $data['emergency_egress_mode'] ?? 'fail_open',
                'status' => $data['status'] ?? 'active',
            ]),
            fn (AcsZone $zone): mixed => event(new AcsZoneCreated($tenantId, $eventId, $zone->id)),
        );
    }
}
