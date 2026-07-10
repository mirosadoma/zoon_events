<?php

namespace App\Modules\AccessControl\Application\Actions;

use App\Modules\AccessControl\Domain\Events\AcsZoneUpdated;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsZone;
use App\Modules\Audit\Application\AuditedTransaction;

final readonly class UpdateAcsZoneAction
{
    public function __construct(private AuditedTransaction $audited) {}

    /**
     * @param  array{
     *     name?: string,
     *     anti_passback_enabled?: bool,
     *     unavailability_mode?: string,
     *     emergency_egress_mode?: string,
     *     status?: string,
     * }  $data
     */
    public function execute(AcsZone $zone, array $data): AcsZone
    {
        return $this->audited->run(
            function () use ($zone, $data): AcsZone {
                $updates = array_intersect_key($data, array_flip([
                    'name',
                    'anti_passback_enabled',
                    'unavailability_mode',
                    'emergency_egress_mode',
                    'status',
                ]));

                if ($updates !== []) {
                    $zone->forceFill($updates)->save();
                }

                return $zone->refresh();
            },
            fn (AcsZone $updated): mixed => event(new AcsZoneUpdated($zone->tenant_id, $zone->event_id, $updated->id)),
        );
    }
}
