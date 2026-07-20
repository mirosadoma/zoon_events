<?php

namespace App\Modules\VenueMarketplace\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class OwnerVenueAssetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $publication = $this->relationLoaded('publications') ? $this->publications->first() : null;
        $binding = $this->relationLoaded('binding') ? $this->binding : null;

        return [
            'public_id' => $this->public_id,
            'asset_type' => $this->asset_type,
            'name_en' => $this->name_en,
            'name_ar' => $this->name_ar,
            'description_en' => $this->description_en,
            'description_ar' => $this->description_ar,
            'location_en' => $this->location_en,
            'location_ar' => $this->location_ar,
            'capabilities' => array_values($this->capabilities ?? []),
            'capacity_per_minute' => $this->capacity_per_minute,
            'operational_status' => $this->operational_status,
            'pricing_model' => $this->pricing_model,
            'price_minor' => (int) $this->price_minor,
            'currency' => $this->currency,
            'publication_status' => match ($publication?->status) {
                'active' => 'published',
                'withdrawn' => 'withdrawn',
                default => 'private',
            },
            'publication_version' => $publication?->publication_version,
            'binding_status' => $this->bindingStatus($binding),
            'availability' => $this->relationLoaded('availabilityWindows')
                ? AvailabilityWindowResource::collection($this->availabilityWindows)->resolve($request)
                : [],
            'version' => (int) $this->version,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function bindingStatus(mixed $binding): string
    {
        if ($binding === null) {
            return 'missing';
        }

        if ($binding->control_family === 'catalog_only') {
            return 'catalog_only';
        }

        return in_array($binding->status, ['active', 'disabled', 'invalid'], true)
            ? $binding->status
            : 'invalid';
    }
}
