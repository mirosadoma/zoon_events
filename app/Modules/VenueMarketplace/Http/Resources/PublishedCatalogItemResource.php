<?php

namespace App\Modules\VenueMarketplace\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublishedCatalogItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'publication_public_id' => $this->public_id,
            'publication_version' => (int) $this->publication_version,
            'venue_public_id' => $this->venue_public_id,
            'asset_public_id' => $this->asset_public_id,
            'venue_name' => ['en' => $this->venue_name_en, 'ar' => $this->venue_name_ar],
            'venue_description' => ['en' => $this->venue_description_en, 'ar' => $this->venue_description_ar],
            'asset_name' => ['en' => $this->asset_name_en, 'ar' => $this->asset_name_ar],
            'asset_description' => ['en' => $this->asset_description_en, 'ar' => $this->asset_description_ar],
            'address' => ['en' => $this->address_en, 'ar' => $this->address_ar],
            'location' => ['en' => $this->location_en, 'ar' => $this->location_ar],
            'country_code' => $this->country_code,
            'city_code' => $this->city_code,
            'timezone' => $this->timezone,
            'asset_type' => $this->asset_type,
            'capabilities' => $this->relationLoaded('capabilities')
                ? $this->capabilities->pluck('capability_code')->values()->all()
                : [],
            'capacity_per_minute' => $this->capacity_per_minute,
            'pricing' => [
                'model' => $this->pricing_model,
                'amount_minor' => (int) $this->price_minor,
                'currency' => $this->currency,
            ],
            'available_for_requested_window' => $this->available_for_requested_window,
            'public_contact' => $this->public_contact,
            'published_at' => $this->published_at?->toIso8601String(),
        ];
    }
}
