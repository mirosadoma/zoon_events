<?php

namespace App\Modules\VenueMarketplace\Application\Actions;

use App\Modules\Audit\Application\AuditedTransaction;
use App\Modules\VenueMarketplace\Application\Audit\MarketplaceAuditEvent;
use App\Modules\VenueMarketplace\Domain\Events\DisputeResolved;
use App\Modules\VenueMarketplace\Domain\Exceptions\MarketplaceDomainException;
use App\Modules\VenueMarketplace\Domain\Services\MarketplaceDisputeStateMachine;
use App\Modules\VenueMarketplace\Http\Problems\Phase6Problem;
use App\Modules\VenueMarketplace\Infrastructure\Audit\DatabaseMarketplaceAuditWriter;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\MarketplaceDispute;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\MarketplaceDisputeEvent;
use Illuminate\Support\Facades\Event;

final readonly class ResolveMarketplaceDisputeAction
{
    public function __construct(
        private AuditedTransaction $transactions,
        private MarketplaceDisputeStateMachine $states,
        private DatabaseMarketplaceAuditWriter $audit,
    ) {}

    public function execute(
        int $platformActorUserId,
        string $disputePublicId,
        string $decision,
        string $resolutionCode,
        string $resolutionSummary,
        string $idempotencyKey,
        string $correlationId,
    ): MarketplaceDispute {
        if (! in_array($decision, ['resolve', 'reject'], true)) {
            throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_DISPUTE_STATE_CONFLICT);
        }

        $this->states->assertValidReasonCode($resolutionCode);
        $this->states->assertValidReason($resolutionSummary);

        $targetStatus = $decision === 'resolve' ? 'resolved' : 'rejected';
        $resolvedDisputeStatus = 'resolved';

        return $this->transactions->run(
            function () use (
                $platformActorUserId, $disputePublicId, $targetStatus,
                $resolutionCode, $resolutionSummary, $resolvedDisputeStatus,
            ): MarketplaceDispute {
                $dispute = MarketplaceDispute::query()
                    ->withoutGlobalScopes()
                    ->where('public_id', $disputePublicId)
                    ->lockForUpdate()
                    ->first();

                if ($dispute === null) {
                    throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_DISPUTE_NOT_FOUND);
                }

                $this->states->transition($dispute->status, $targetStatus, 'platform');

                $now = now();
                $dispute->forceFill([
                    'status' => $targetStatus,
                    'resolution_code' => $resolutionCode,
                    'resolution_summary' => $resolutionSummary,
                    'resolved_at' => $now,
                ])->save();

                MarketplaceDisputeEvent::query()->forceCreate([
                    'tenant_id' => $dispute->tenant_id,
                    'organizer_tenant_id' => $dispute->organizer_tenant_id,
                    'marketplace_dispute_id' => $dispute->id,
                    'event_type' => $targetStatus,
                    'actor_scope' => 'platform',
                    'actor_user_id' => $platformActorUserId,
                    'visibility' => 'participants',
                    'reason_code' => $resolutionCode,
                    'created_at' => $now,
                ]);

                $this->updateProjectedDisputeStatus($dispute, $resolvedDisputeStatus);

                return $dispute->load('events');
            },
            function (MarketplaceDispute $dispute) use (
                $platformActorUserId, $targetStatus, $correlationId,
            ): void {
                $this->writeAudit($dispute, $platformActorUserId, $targetStatus, $correlationId);
                Event::dispatch(new DisputeResolved(
                    $dispute->public_id,
                    $targetStatus,
                    (int) $dispute->tenant_id,
                    (int) $dispute->organizer_tenant_id,
                    $platformActorUserId,
                    $dispute->resolution_code,
                    $correlationId,
                ));
            },
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

    private function writeAudit(
        MarketplaceDispute $dispute,
        int $actorUserId,
        string $targetStatus,
        string $correlationId,
    ): void {
        foreach (['owner', 'organizer', 'platform'] as $scope) {
            $this->audit->write(new MarketplaceAuditEvent(
                "dispute.{$targetStatus}",
                $scope,
                'succeeded',
                $correlationId,
                $dispute->public_id,
                ['status' => $targetStatus, 'resolution_code' => $dispute->resolution_code],
                ownerTenantId: (int) $dispute->tenant_id,
                organizerTenantId: (int) $dispute->organizer_tenant_id,
                actorUserId: $actorUserId,
            ));
        }
    }
}
