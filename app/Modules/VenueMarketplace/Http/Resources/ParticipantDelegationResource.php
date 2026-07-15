<?php

namespace App\Modules\VenueMarketplace\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ParticipantDelegationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'status' => $this->status,
            'starts_at' => $this->starts_at?->toISOString(),
            'ends_at' => $this->ends_at?->toISOString(),
            'revoked_at' => $this->revoked_at?->toISOString(),
            'expired_at' => $this->expired_at?->toISOString(),
            'degraded_reason_code' => $this->degraded_reason_code,
            'version' => (int) $this->version,
            'resources' => $this->whenLoaded('resources', fn () => $this->resources->map(fn ($resource) => [
                'resource_module' => $resource->resource_module,
                'resource_type' => $resource->resource_type,
                'resource_public_reference' => $resource->resource_public_reference,
                'granted_capabilities' => $resource->granted_capabilities,
                'provisioning_status' => $resource->provisioning_status,
                'provisioned_at' => $resource->provisioned_at?->toISOString(),
                'released_at' => $resource->released_at?->toISOString(),
            ])->all()),
        ];
    }
}
