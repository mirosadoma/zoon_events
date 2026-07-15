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
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\SettlementStatement;
use Illuminate\Support\Str;

final readonly class OpenMarketplaceDisputeAction
{
    public function __construct(
        private AuditedTransaction $transactions,
        private MarketplaceDisputeStateMachine $states,
        private DatabaseMarketplaceAuditWriter $audit,
    ) {}

    public function execute(
        int $actorTenantId,
        int $actorUserId,
        string $statementPublicId,
        string $reasonCode,
        string $reason,
        string $idempotencyKey,
        string $correlationId,
    ): MarketplaceDispute {
        $this->states->assertValidReasonCode($reasonCode);
        $this->states->assertValidReason($reason);

        return $this->transactions->run(
            function () use (
                $actorTenantId, $actorUserId, $statementPublicId,
                $reasonCode, $reason, $idempotencyKey,
            ): MarketplaceDispute {
                $statement = SettlementStatement::query()
                    ->forParticipant($actorTenantId)
                    ->where('public_id', $statementPublicId)
                    ->lockForUpdate()
                    ->first();

                if ($statement === null) {
                    throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_STATEMENT_NOT_FOUND);
                }

                if (! $statement->isIssued()) {
                    throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_STATEMENT_NOT_READY);
                }

                $existing = MarketplaceDispute::query()
                    ->withoutGlobalScopes()
                    ->where('tenant_id', $statement->tenant_id)
                    ->where('organizer_tenant_id', $statement->organizer_tenant_id)
                    ->where('settlement_statement_id', $statement->id)
                    ->whereIn('status', ['open', 'under_review'])
                    ->first();

                if ($existing !== null) {
                    throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_DISPUTE_STATE_CONFLICT);
                }

                $actorScope = (int) $statement->tenant_id === $actorTenantId ? 'owner' : 'organizer';
                $now = now();

                $dispute = MarketplaceDispute::query()->forceCreate([
                    'tenant_id' => $statement->tenant_id,
                    'organizer_tenant_id' => $statement->organizer_tenant_id,
                    'public_id' => (string) Str::ulid(),
                    'rental_request_id' => $statement->rental_request_id,
                    'settlement_statement_id' => $statement->id,
                    'reported_by_tenant_id' => $actorTenantId,
                    'reported_by_user_id' => $actorUserId,
                    'status' => 'open',
                    'reason_code' => $reasonCode,
                    'reason' => $reason,
                    'opened_at' => $now,
                ]);

                MarketplaceDisputeEvent::query()->forceCreate([
                    'tenant_id' => $statement->tenant_id,
                    'organizer_tenant_id' => $statement->organizer_tenant_id,
                    'marketplace_dispute_id' => $dispute->id,
                    'event_type' => 'opened',
                    'actor_scope' => $actorScope,
                    'actor_user_id' => $actorUserId,
                    'visibility' => 'participants',
                    'reason_code' => $reasonCode,
                    'created_at' => $now,
                ]);

                $statement->forceFill(['dispute_status' => 'open'])->save();

                $rental = $statement->rental()->withoutGlobalScopes()->first();
                if ($rental !== null) {
                    $rental->forceFill(['dispute_status' => 'open'])->save();
                }

                return $dispute->load('events');
            },
            fn (MarketplaceDispute $dispute) => $this->writeAudit(
                $dispute, $actorUserId, $correlationId,
            ),
        );
    }

    private function writeAudit(MarketplaceDispute $dispute, int $actorUserId, string $correlationId): void
    {
        foreach (['owner', 'organizer'] as $scope) {
            $this->audit->write(new MarketplaceAuditEvent(
                'dispute.opened',
                $scope,
                'succeeded',
                $correlationId,
                $dispute->public_id,
                ['status' => 'open', 'reason_code' => $dispute->reason_code],
                ownerTenantId: (int) $dispute->tenant_id,
                organizerTenantId: (int) $dispute->organizer_tenant_id,
                actorUserId: $actorUserId,
            ));
        }
    }
}
