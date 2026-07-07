<?php

namespace App\Modules\AccessControl\Http\Resources;

use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsLane;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AcsLane */
final class AcsLaneResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'zone_id' => $this->zone_id,
            'name' => $this->name,
            'external_acs_lane_id' => $this->external_acs_lane_id,
            'gate_type' => $this->gate_type,
            'access_direction' => $this->access_direction,
            'is_admission_lane' => $this->is_admission_lane,
            'status' => $this->status,
            'health_status' => $this->health_status,
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
        ];
    }
}
