<?php

namespace App\Modules\VenueMarketplace\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ParticipantRentalLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'publication_public_id' => $this->publication_public_id,
            'publication_version' => (int) $this->publication_version,
            'asset_public_id' => $this->asset_public_id,
            'asset_name' => ['en' => $this->name_en, 'ar' => $this->name_ar],
            'asset_type' => $this->asset_type,
            'capabilities' => $this->selected_capabilities,
            'pricing_model' => $this->pricing_model,
            'unit_price_minor' => (int) $this->unit_price_minor,
            'quantity' => (int) $this->quantity,
            'billable_units' => (int) $this->billable_units,
            'line_total_minor' => (int) $this->line_total_minor,
            'currency' => $this->currency,
        ];
    }
}
