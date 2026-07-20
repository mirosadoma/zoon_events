<?php

namespace App\Modules\VenueMarketplace\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class MarketplaceQuoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'quote_digest' => $this->resource['quote_digest'],
            'quote_version' => $this->resource['quote_version'],
            'venue_public_id' => $this->resource['venue_public_id'],
            'venue_timezone' => $this->resource['venue_timezone'],
            'requested_start_at' => $this->resource['requested_start_at'],
            'requested_end_at' => $this->resource['requested_end_at'],
            'lines' => $this->resource['lines'],
            'total_minor' => $this->resource['total_minor'],
            'currency' => $this->resource['currency'],
            'expires_at' => $this->resource['expires_at'] ?? null,
        ];
    }
}
