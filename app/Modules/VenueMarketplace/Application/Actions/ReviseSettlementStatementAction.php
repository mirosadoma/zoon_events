<?php

namespace App\Modules\VenueMarketplace\Application\Actions;

use App\Modules\Audit\Application\AuditedTransaction;
use App\Modules\VenueMarketplace\Application\Audit\MarketplaceAuditEvent;
use App\Modules\VenueMarketplace\Domain\Events\StatementRevised;
use App\Modules\VenueMarketplace\Domain\Exceptions\MarketplaceDomainException;
use App\Modules\VenueMarketplace\Domain\Services\SettlementRevisionPolicy;
use App\Modules\VenueMarketplace\Http\Problems\Phase6Problem;
use App\Modules\VenueMarketplace\Infrastructure\Audit\DatabaseMarketplaceAuditWriter;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\MarketplaceDispute;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\SettlementStatement;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\SettlementStatementLine;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

final readonly class ReviseSettlementStatementAction
{
    public function __construct(
        private AuditedTransaction $transactions,
        private SettlementRevisionPolicy $policy,
        private DatabaseMarketplaceAuditWriter $audit,
    ) {}

    /**
     * @param list<array{asset_public_id: string, unit_price_minor: int, billable_units: int}> $lines
     */
    public function execute(
        int $platformActorUserId,
        string $statementPublicId,
        string $disputePublicId,
        string $reasonCode,
        array $lines,
        string $idempotencyKey,
        string $correlationId,
    ): SettlementStatement {
        $this->policy->assertReasonCode($reasonCode);

        return $this->transactions->run(
            function () use (
                $platformActorUserId, $statementPublicId, $disputePublicId,
                $reasonCode, $lines,
            ): SettlementStatement {
                $original = SettlementStatement::query()
                    ->withoutGlobalScopes()
                    ->where('public_id', $statementPublicId)
                    ->with('lines')
                    ->lockForUpdate()
                    ->first();

                if ($original === null) {
                    throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_STATEMENT_NOT_FOUND);
                }

                $this->policy->assertCanRevise($original->status, (int) $original->revision);

                $dispute = MarketplaceDispute::query()
                    ->withoutGlobalScopes()
                    ->where('public_id', $disputePublicId)
                    ->where('tenant_id', $original->tenant_id)
                    ->where('organizer_tenant_id', $original->organizer_tenant_id)
                    ->where('settlement_statement_id', $original->id)
                    ->first();

                if ($dispute === null) {
                    throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_DISPUTE_NOT_FOUND);
                }

                $this->policy->assertDisputeLinked($dispute->status);

                $linesByAsset = collect($lines)->keyBy('asset_public_id');
                $revisedLines = [];
                foreach ($original->lines as $originalLine) {
                    $override = $linesByAsset->get($originalLine->asset_public_id);
                    $revisedLines[] = [
                        'rental_asset_id' => $originalLine->rental_asset_id,
                        'publication_public_id' => $originalLine->publication_public_id,
                        'publication_version' => (int) $originalLine->publication_version,
                        'asset_public_id' => $originalLine->asset_public_id,
                        'asset_type' => $originalLine->asset_type,
                        'name_en' => $originalLine->name_en,
                        'name_ar' => $originalLine->name_ar,
                        'pricing_model' => $originalLine->pricing_model,
                        'unit_price_minor' => $override ? (int) $override['unit_price_minor'] : (int) $originalLine->unit_price_minor,
                        'billable_units' => $override ? (int) $override['billable_units'] : (int) $originalLine->billable_units,
                        'currency' => $originalLine->currency,
                    ];
                }

                foreach ($revisedLines as &$line) {
                    $line['line_total_minor'] = $line['unit_price_minor'] * $line['billable_units'];
                }
                unset($line);

                $newTotal = $this->policy->computeTotal($revisedLines);
                $nextRevision = $this->policy->nextRevision((int) $original->revision);

                $now = now();
                $revision = SettlementStatement::query()->forceCreate([
                    'tenant_id' => $original->tenant_id,
                    'organizer_tenant_id' => $original->organizer_tenant_id,
                    'public_id' => (string) Str::ulid(),
                    'rental_request_id' => $original->rental_request_id,
                    'statement_number' => 'STMT-'.strtoupper(Str::ulid()->toBase32()),
                    'revision' => $nextRevision,
                    'supersedes_statement_id' => $original->id,
                    'status' => 'issued',
                    'dispute_status' => $original->dispute_status,
                    'rental_outcome' => $original->rental_outcome,
                    'venue_timezone' => $original->venue_timezone,
                    'agreed_start_at' => $original->agreed_start_at,
                    'agreed_end_at' => $original->agreed_end_at,
                    'currency' => $original->currency,
                    'agreed_total_minor' => $newTotal,
                    'issued_at' => $now,
                    'generated_by' => 'actor',
                    'created_at' => $now,
                ]);

                foreach ($revisedLines as $revisedLine) {
                    SettlementStatementLine::query()->forceCreate([
                        'tenant_id' => $original->tenant_id,
                        'organizer_tenant_id' => $original->organizer_tenant_id,
                        'settlement_statement_id' => $revision->id,
                        ...$revisedLine,
                        'created_at' => $now,
                    ]);
                }

                $original->forceFill(['status' => 'superseded'])->save();

                return $revision->load('lines');
            },
            function (SettlementStatement $revision) use (
                $platformActorUserId, $correlationId,
            ): void {
                $this->writeAudit($revision, $platformActorUserId, $correlationId);
                Event::dispatch(new StatementRevised(
                    $revision->public_id,
                    (int) $revision->revision,
                    (int) $revision->tenant_id,
                    (int) $revision->organizer_tenant_id,
                    $platformActorUserId,
                    $correlationId,
                ));
            },
        );
    }

    private function writeAudit(
        SettlementStatement $revision,
        int $actorUserId,
        string $correlationId,
    ): void {
        foreach (['owner', 'organizer', 'platform'] as $scope) {
            $this->audit->write(new MarketplaceAuditEvent(
                'statement.revised',
                $scope,
                'succeeded',
                $correlationId,
                $revision->public_id,
                [
                    'revision' => (int) $revision->revision,
                    'currency' => $revision->currency,
                    'agreed_total_minor' => (int) $revision->agreed_total_minor,
                ],
                ownerTenantId: (int) $revision->tenant_id,
                organizerTenantId: (int) $revision->organizer_tenant_id,
                actorUserId: $actorUserId,
            ));
        }
    }
}
