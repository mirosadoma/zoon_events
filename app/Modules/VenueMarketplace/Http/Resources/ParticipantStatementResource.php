<?php

namespace App\Modules\VenueMarketplace\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ParticipantStatementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'statement_number' => $this->statement_number,
            'revision' => (int) $this->revision,
            'status' => $this->status,
            'dispute_status' => $this->dispute_status,
            'supersedes_statement_public_id' => $this->whenLoaded(
                'supersedes',
                fn () => $this->supersedes?->public_id,
            ),
            'rental_public_id' => $this->whenLoaded(
                'rental',
                fn () => $this->rental?->public_id,
                $this->rental_public_id ?? null,
            ),
            'rental_outcome' => $this->rental_outcome,
            'agreed_start_at' => $this->agreed_start_at?->toISOString(),
            'agreed_end_at' => $this->agreed_end_at?->toISOString(),
            'currency' => $this->currency,
            'agreed_total_minor' => (int) $this->agreed_total_minor,
            'funds_moved' => false,
            'lines' => ParticipantRentalLineResource::collection($this->whenLoaded('lines')),
            'issued_at' => $this->issued_at?->toISOString(),
        ];
    }
}
