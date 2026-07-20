<?php

namespace App\Modules\VenueMarketplace\Application\Listeners;

use App\Modules\VenueMarketplace\Application\Audit\MarketplaceAuditEvent;
use App\Modules\VenueMarketplace\Application\Audit\MarketplaceAuditWriter;
use App\Modules\VenueMarketplace\Domain\Events\DisputeOpened;
use App\Modules\VenueMarketplace\Domain\Events\DisputeResolved;
use App\Modules\VenueMarketplace\Domain\Events\StatementGenerated;
use App\Modules\VenueMarketplace\Domain\Events\StatementRevised;

final readonly class WriteSettlementDisputeAudit
{
    public function __construct(private MarketplaceAuditWriter $audit) {}

    public function handle(
        StatementGenerated|StatementRevised|DisputeOpened|DisputeResolved $event,
    ): void {
        match (true) {
            $event instanceof StatementGenerated => $this->handleStatementGenerated($event),
            $event instanceof StatementRevised => $this->handleStatementRevised($event),
            $event instanceof DisputeOpened => $this->handleDisputeOpened($event),
            $event instanceof DisputeResolved => $this->handleDisputeResolved($event),
        };
    }

    private function handleStatementGenerated(StatementGenerated $event): void
    {
        foreach (['owner', 'organizer'] as $scope) {
            $this->audit->write(new MarketplaceAuditEvent(
                'statement.generated',
                $scope,
                'succeeded',
                $event->correlationId,
                $event->statementPublicId,
                [
                    'revision' => $event->revision,
                    'rental_outcome' => $event->rentalOutcome,
                    'currency' => $event->currency,
                    'agreed_total_minor' => $event->agreedTotalMinor,
                ],
                ownerTenantId: $event->ownerTenantId(),
                organizerTenantId: $event->organizerTenantId(),
            ));
        }
    }

    private function handleStatementRevised(StatementRevised $event): void
    {
        foreach (['owner', 'organizer', 'platform'] as $scope) {
            $this->audit->write(new MarketplaceAuditEvent(
                'statement.revised',
                $scope,
                'succeeded',
                $event->correlationId,
                $event->statementPublicId,
                ['revision' => $event->revision],
                ownerTenantId: $event->ownerTenantId(),
                organizerTenantId: $event->organizerTenantId(),
                actorUserId: $event->actorUserId,
            ));
        }
    }

    private function handleDisputeOpened(DisputeOpened $event): void
    {
        foreach (['owner', 'organizer'] as $scope) {
            $this->audit->write(new MarketplaceAuditEvent(
                'dispute.opened',
                $scope,
                'succeeded',
                $event->correlationId,
                $event->disputePublicId,
                ['status' => 'open', 'reason_code' => $event->reasonCode],
                ownerTenantId: $event->ownerTenantId(),
                organizerTenantId: $event->organizerTenantId(),
                actorUserId: $event->actorUserId,
            ));
        }
    }

    private function handleDisputeResolved(DisputeResolved $event): void
    {
        foreach (['owner', 'organizer', 'platform'] as $scope) {
            $this->audit->write(new MarketplaceAuditEvent(
                "dispute.{$event->decision}",
                $scope,
                'succeeded',
                $event->correlationId,
                $event->disputePublicId,
                ['status' => $event->decision, 'resolution_code' => $event->resolutionCode],
                ownerTenantId: $event->ownerTenantId(),
                organizerTenantId: $event->organizerTenantId(),
                actorUserId: $event->actorUserId,
            ));
        }
    }
}
