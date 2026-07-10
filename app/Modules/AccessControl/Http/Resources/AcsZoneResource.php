<?php

namespace App\Modules\AccessControl\Http\Resources;

use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsZone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AcsZone */
final class AcsZoneResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'external_acs_zone_id' => $this->external_acs_zone_id,
            'anti_passback_enabled' => $this->anti_passback_enabled,
            'unavailability_mode' => $this->unavailability_mode,
            'emergency_egress_mode' => $this->emergency_egress_mode,
            'status' => $this->status,
        ];
    }
}
