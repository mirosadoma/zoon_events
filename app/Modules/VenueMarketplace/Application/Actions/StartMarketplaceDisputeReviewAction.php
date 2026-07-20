<?php

namespace App\Modules\VenueMarketplace\Application\Actions;

use App\Modules\Audit\Application\AuditedTransaction;
use App\Modules\VenueMarketplace\Application\Audit\MarketplaceAuditEvent;
use App\Modules\VenueMarketplace\Domain\Exceptions\MarketplaceDomainException;
use App\Modules\VenueMarketplace\Domain\Services\MarketplaceDisputeStateMachine;
use App\Modules\VenueMarketplace\Http\Problems\Phase6Problem;
use App\Modules\VenueMarketplace\Infrastructure\Audit\DatabaseMarketplaceAuditWriter;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\MarketplaceDispute;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\MarketplaceDisputeEvent;

final readonly class StartMarketplaceDisputeReviewAction
{
    public function __construct(
        private AuditedTransaction $transactions,
        private MarketplaceDisputeStateMachine $states,
        private DatabaseMarketplaceAuditWriter $audit,
    ) {}

    public function execute(
        int $platformActorUserId,
        string $disputePublicId,
        string $idempotencyKey,
        string $correlationId,
    ): MarketplaceDispute {
        return $this->transactions->run(
            function () use ($platformActorUserId, $disputePublicId): MarketplaceDispute {
                $dispute = MarketplaceDispute::query()
                    ->withoutGlobalScopes()
                    ->where('public_id', $disputePublicId)
                    ->lockForUpdate()
                    ->first();

                if ($dispute === null) {
                    throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_DISPUTE_NOT_FOUND);
                }

                if ($dispute->status === 'under_review') {
                    return $dispute->load('events');
                }

                $this->states->transition($dispute->status, 'under_review', 'platform');

                $now = now();
                $dispute->forceFill([
                    'status' => 'under_review',
                    'assigned_platform_user_id' => $platformActorUserId,
                    'review_started_at' => $now,
                ])->save();

                MarketplaceDisputeEvent::query()->forceCreate([
                    'tenant_id' => $dispute->tenant_id,
                    'organizer_tenant_id' => $dispute->organizer_tenant_id,
                    'marketplace_dispute_id' => $dispute->id,
                    'event_type' => 'review_started',
                    'actor_scope' => 'platform',
                    'actor_user_id' => $platformActorUserId,
                    'visibility' => 'participants',
                    'created_at' => $now,
                ]);

                $this->updateProjectedDisputeStatus($dispute, 'under_review');

                return $dispute->load('events');
            },
            fn (MarketplaceDispute $dispute) => $this->writeAudit(
                $dispute, $platformActorUserId, $correlationId,
            ),
        );
    }

    private function updateProjectedDisputeStatus(MarketplaceDispute $dispute, string $status): void
    {
        $dispute->statement()
            ->withoutGlobalScopes()
            ->first()
            ?->forceFill(['dispute_status' => $status])
            ->save();

        $dispute->rental()
            ->withoutGlobalScopes()
            ->first()
            ?->forceFill(['dispute_status' => $status])
            ->save();
    }

    private function writeAudit(MarketplaceDispute $dispute, int $actorUserId, string $correlationId): void
    {
        foreach (['owner', 'organizer', 'platform'] as $scope) {
            $this->audit->write(new MarketplaceAuditEvent(
                'dispute.review_started',
                $scope,
                'succeeded',
                $correlationId,
                $dispute->public_id,
                ['status' => 'under_review'],
                ownerTenantId: (int) $dispute->tenant_id,
                organizerTenantId: (int) $dispute->organizer_tenant_id,
                actorUserId: $actorUserId,
            ));
        }
    }
}
