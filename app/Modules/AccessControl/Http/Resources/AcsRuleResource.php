<?php

namespace App\Modules\AccessControl\Http\Resources;

use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsAuthorizationRule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AcsAuthorizationRule */
final class AcsRuleResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticket_type_id' => $this->ticket_type_id,
            'attendee_type' => $this->attendee_type,
            'zone_id' => $this->zone_id,
            'lane_id' => $this->lane_id,
            'access_direction' => $this->access_direction,
            'anti_passback_exempt' => $this->anti_passback_exempt,
            'valid_from' => $this->valid_from?->toIso8601String(),
            'valid_until' => $this->valid_until?->toIso8601String(),
            'status' => $this->status,
        ];
    }
}
