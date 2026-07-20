<?php

namespace App\Modules\VenueMarketplace\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ParticipantRentalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'viewer_role' => $this->viewer_role,
            'event' => $this->event_snapshot,
            'venue' => [
                'public_id' => $this->venue_public_id,
                'name' => ['en' => $this->venue_name_en, 'ar' => $this->venue_name_ar],
                'timezone' => $this->venue_timezone,
            ],
            'status' => $this->status,
            'dispute_status' => $this->dispute_status,
            'requested_start_at' => $this->requested_start_at?->toISOString(),
            'requested_end_at' => $this->requested_end_at?->toISOString(),
            'currency' => $this->currency,
            'total_minor' => (int) $this->total_minor,
            'decision_reason' => $this->decision_reason,
            'version' => (int) $this->version,
            'lines' => ParticipantRentalLineResource::collection($this->whenLoaded('assets')),
            'delegation' => null,
            'submitted_at' => $this->submitted_at?->toISOString(),
            'approved_at' => $this->approved_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
        ];
    }
}
