<?php

namespace App\Modules\AccessControl\Http\Resources;

use App\Modules\AccessControl\Infrastructure\Persistence\Models\EmergencyEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EmergencyEvent */
final class EmergencyEventResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'zone_id' => $this->zone_id,
            'signal_source' => $this->signal_source,
            'behavior_applied' => $this->behavior_applied,
            'raised_at' => $this->raised_at?->toIso8601String(),
            'cleared_at' => $this->cleared_at?->toIso8601String(),
        ];
    }
}
