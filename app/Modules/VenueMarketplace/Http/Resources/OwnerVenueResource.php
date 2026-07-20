<?php

namespace App\Modules\VenueMarketplace\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class OwnerVenueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'name_en' => $this->name_en,
            'name_ar' => $this->name_ar,
            'description_en' => $this->description_en,
            'description_ar' => $this->description_ar,
            'address_en' => $this->address_en,
            'address_ar' => $this->address_ar,
            'country_code' => $this->country_code,
            'city_code' => $this->city_code,
            'timezone' => $this->timezone,
            'business_contact_name' => $this->business_contact_name,
            'business_contact_email' => $this->business_contact_email,
            'business_contact_phone' => $this->business_contact_phone,
            'publish_contact' => (bool) $this->publish_contact,
            'status' => $this->status,
            'version' => (int) $this->version,
            'asset_count' => (int) ($this->asset_count ?? 0),
            'published_asset_count' => (int) ($this->published_asset_count ?? 0),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
