<?php

namespace App\Modules\VenueMarketplace\Http\Resources;

use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\MarketplaceDisputeEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ParticipantDisputeResource extends JsonResource
{
    private bool $includePlatformNotes = false;

    public function withPlatformNotes(): static
    {
        $this->includePlatformNotes = true;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $events = $this->whenLoaded('events', function () {
            $collection = $this->includePlatformNotes
                ? $this->events
                : $this->events->filter(fn (MarketplaceDisputeEvent $e): bool => $e->isParticipantVisible());

            return $collection->map(fn (MarketplaceDisputeEvent $event): array => [
                'event_type' => $event->event_type,
                'actor_scope' => $event->actor_scope,
                'visibility' => $event->visibility,
                'reason_code' => $event->reason_code,
                'note' => $event->isParticipantVisible() || $this->includePlatformNotes
                    ? $event->note
                    : null,
                'created_at' => $event->created_at?->toISOString(),
            ])->values()->all();
        }, []);

        return [
            'public_id' => $this->public_id,
            'statement_public_id' => $this->whenLoaded(
                'statement',
                fn () => $this->statement?->public_id,
            ),
            'rental_public_id' => $this->whenLoaded(
                'rental',
                fn () => $this->rental?->public_id,
            ),
            'status' => $this->status,
            'reason_code' => $this->reason_code,
            'reason' => $this->reason,
            'resolution_code' => $this->resolution_code,
            'resolution_summary' => $this->resolution_summary,
            'opened_at' => $this->opened_at?->toISOString(),
            'resolved_at' => $this->resolved_at?->toISOString(),
            'events' => $events,
        ];
    }
}
