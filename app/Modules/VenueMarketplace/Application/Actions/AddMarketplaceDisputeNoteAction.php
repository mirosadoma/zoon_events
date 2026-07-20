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

final readonly class AddMarketplaceDisputeNoteAction
{
    public function __construct(
        private AuditedTransaction $transactions,
        private MarketplaceDisputeStateMachine $states,
        private DatabaseMarketplaceAuditWriter $audit,
    ) {}

    public function execute(
        int $platformActorUserId,
        string $disputePublicId,
        string $note,
        string $visibility,
        string $idempotencyKey,
        string $correlationId,
    ): MarketplaceDispute {
        $this->states->assertValidNote($note);
        $this->states->assertValidVisibility($visibility);

        return $this->transactions->run(
            function () use (
                $platformActorUserId, $disputePublicId, $note, $visibility,
            ): MarketplaceDispute {
                $dispute = MarketplaceDispute::query()
                    ->withoutGlobalScopes()
                    ->where('public_id', $disputePublicId)
                    ->lockForUpdate()
                    ->first();

                if ($dispute === null) {
                    throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_DISPUTE_NOT_FOUND);
                }

                if (! $dispute->isActive()) {
                    throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_DISPUTE_STATE_CONFLICT);
                }

                MarketplaceDisputeEvent::query()->forceCreate([
                    'tenant_id' => $dispute->tenant_id,
                    'organizer_tenant_id' => $dispute->organizer_tenant_id,
                    'marketplace_dispute_id' => $dispute->id,
                    'event_type' => 'note_added',
                    'actor_scope' => 'platform',
                    'actor_user_id' => $platformActorUserId,
                    'visibility' => $visibility,
                    'note' => $note,
                    'created_at' => now(),
                ]);

                return $dispute->load('events');
            },
            fn (MarketplaceDispute $dispute) => $this->writeAudit(
                $dispute, $platformActorUserId, $visibility, $correlationId,
            ),
        );
    }

    private function writeAudit(
        MarketplaceDispute $dispute,
        int $actorUserId,
        string $visibility,
        string $correlationId,
    ): void {
        $scopes = ['platform'];
        if ($visibility === 'participants') {
            $scopes = ['owner', 'organizer', 'platform'];
        }

        foreach ($scopes as $scope) {
            $this->audit->write(new MarketplaceAuditEvent(
                'dispute.note_added',
                $scope,
                'succeeded',
                $correlationId,
                $dispute->public_id,
                ['status' => $dispute->status, 'visibility' => $visibility],
                ownerTenantId: (int) $dispute->tenant_id,
                organizerTenantId: (int) $dispute->organizer_tenant_id,
                actorUserId: $actorUserId,
            ));
        }
    }
}
