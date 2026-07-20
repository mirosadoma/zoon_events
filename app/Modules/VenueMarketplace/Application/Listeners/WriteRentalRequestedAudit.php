<?php

namespace App\Modules\VenueMarketplace\Application\Listeners;

use App\Modules\VenueMarketplace\Application\Audit\MarketplaceAuditEvent;
use App\Modules\VenueMarketplace\Application\Audit\MarketplaceAuditWriter;
use App\Modules\VenueMarketplace\Domain\Events\RentalRequested;

final readonly class WriteRentalRequestedAudit
{
    public function __construct(private MarketplaceAuditWriter $audit) {}

    public function handle(RentalRequested $event): void
    {
        $payload = [
            'event_public_id' => $event->eventPublicId,
            'status' => $event->status,
            'total_minor' => $event->totalMinor,
            'currency' => $event->currency,
        ];
        foreach (['owner', 'organizer'] as $scope) {
            $this->audit->write(new MarketplaceAuditEvent(
                'rental.requested',
                $scope,
                'succeeded',
                $event->correlationId,
                $event->rentalPublicId,
                $payload,
                ownerTenantId: $event->ownerTenantId(),
                organizerTenantId: $event->organizerTenantId(),
                actorUserId: $event->actorUserId,
            ));
        }
    }
}
