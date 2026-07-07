<?php

namespace App\Modules\AccessControl\Http\Resources;

use App\Modules\AccessControl\Infrastructure\Persistence\Models\AccessEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AccessEvent */
final class AccessEventResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_type' => $this->event_type,
            'decision' => $this->decision === 'n/a' ? null : $this->decision,
            'reason_code' => $this->reason_code,
            'direction' => $this->direction,
            'zone_id' => $this->zone_id,
            'lane_id' => $this->lane_id,
            'credential_id' => $this->credential_id,
            'occurred_at' => $this->occurred_at?->toIso8601String(),
        ];
    }
}
