<?php

namespace App\Modules\AccessControl\Application\Actions;

use App\Modules\AccessControl\Domain\Events\AcsLaneCreated;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsLane;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsZone;
use App\Modules\Audit\Application\AuditedTransaction;
use App\Modules\Shared\Http\Problems\Phase4Problem;
use Illuminate\Support\Str;

final readonly class CreateAcsLaneAction
{
    public function __construct(private AuditedTransaction $audited) {}

    /**
     * @param  array{
     *     zone_id: string,
     *     name: string,
     *     external_acs_lane_id: string,
     *     gate_type: string,
     *     access_direction: string,
     *     is_admission_lane?: bool,
     *     status?: string,
     * }  $data
     */
    public function execute(string $tenantId, string $eventId, array $data): AcsLane
    {
        $zone = AcsZone::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('id', $data['zone_id'])
            ->first();

        if ($zone === null) {
            abort(404);
        }

        if (AcsLane::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('external_acs_lane_id', $data['external_acs_lane_id'])
            ->exists()) {
            throw Phase4Problem::make('acs_duplicate_external_id');
        }

        return $this->audited->run(
            fn (): AcsLane => AcsLane::query()->create([
                'id' => (string) Str::ulid(),
                'tenant_id' => $tenantId,
                'event_id' => $eventId,
                'zone_id' => $zone->id,
                'name' => $data['name'],
                'external_acs_lane_id' => $data['external_acs_lane_id'],
                'gate_type' => $data['gate_type'],
                'access_direction' => $data['access_direction'],
                'is_admission_lane' => $data['is_admission_lane'] ?? false,
                'status' => $data['status'] ?? 'active',
                'health_status' => 'offline',
            ]),
            fn (AcsLane $lane): mixed => event(new AcsLaneCreated($tenantId, $eventId, $lane->id)),
        );
    }
}
