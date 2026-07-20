<?php

namespace App\Modules\VenueMarketplace\Application\Listeners;

use App\Modules\VenueMarketplace\Application\Audit\MarketplaceAuditEvent;
use App\Modules\VenueMarketplace\Application\Audit\MarketplaceAuditWriter;
use App\Modules\VenueMarketplace\Domain\Events\DelegationProvisioned;
use App\Modules\VenueMarketplace\Domain\Events\DelegationReleased;

final readonly class WriteDelegationAudit
{
    public function __construct(private MarketplaceAuditWriter $audit) {}

    public function handleProvisioned(DelegationProvisioned $event): void
    {
        $action = match ($event->status) {
            'degraded' => 'delegation.degraded',
            default => 'delegation.provisioned',
        };

        foreach (['owner', 'organizer'] as $scope) {
            $this->audit->write(new MarketplaceAuditEvent(
                $action,
                $scope,
                'succeeded',
                $event->correlationId,
                $event->delegationPublicId,
                ['status' => $event->status],
                ownerTenantId: $event->ownerTenantId,
                organizerTenantId: $event->organizerTenantId,
            ));
        }
    }

    public function handleReleased(DelegationReleased $event): void
    {
        foreach (['owner', 'organizer'] as $scope) {
            $this->audit->write(new MarketplaceAuditEvent(
                'delegation.released',
                $scope,
                'succeeded',
                $event->correlationId,
                $event->delegationPublicId,
                ['status' => 'released'],
                ownerTenantId: $event->ownerTenantId,
                organizerTenantId: $event->organizerTenantId,
            ));
        }
    }
}
