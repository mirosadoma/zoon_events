<?php

namespace App\Modules\VenueMarketplace\Application\Actions;

use App\Modules\Audit\Application\AuditedTransaction;
use App\Modules\VenueMarketplace\Application\Audit\MarketplaceAuditEvent;
use App\Modules\VenueMarketplace\Domain\Exceptions\MarketplaceDomainException;
use App\Modules\VenueMarketplace\Http\Problems\Phase6Problem;
use App\Modules\VenueMarketplace\Infrastructure\Audit\DatabaseMarketplaceAuditWriter;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\RentalRequest;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\SettlementStatement;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\SettlementStatementLine;
use Illuminate\Support\Str;

final readonly class GenerateSettlementStatementAction
{
    private const TERMINAL_OUTCOMES = ['completed', 'cancelled', 'revoked'];

    public function __construct(
        private AuditedTransaction $transactions,
        private DatabaseMarketplaceAuditWriter $audit,
    ) {}

    public function execute(
        int $ownerTenantId,
        string $rentalPublicId,
        string $correlationId,
    ): SettlementStatement {
        return $this->transactions->run(
            function () use ($ownerTenantId, $rentalPublicId): SettlementStatement {
                $rental = RentalRequest::query()
                    ->withoutGlobalScopes()
                    ->where('tenant_id', $ownerTenantId)
                    ->where('public_id', $rentalPublicId)
                    ->with('assets')
                    ->lockForUpdate()
                    ->first();

                if ($rental === null) {
                    throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_RENTAL_NOT_FOUND);
                }

                if (! in_array($rental->status, self::TERMINAL_OUTCOMES, true)) {
                    throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_STATEMENT_NOT_READY);
                }

                $existing = SettlementStatement::query()
                    ->withoutGlobalScopes()
                    ->where('tenant_id', $ownerTenantId)
                    ->where('organizer_tenant_id', $rental->organizer_tenant_id)
                    ->where('rental_request_id', $rental->id)
                    ->where('revision', 1)
                    ->first();

                if ($existing !== null) {
                    return $existing->load('lines');
                }

                $now = now();
                $statementNumber = 'STMT-'.strtoupper(Str::ulid()->toBase32());

                $statement = SettlementStatement::query()->forceCreate([
                    'tenant_id' => $ownerTenantId,
                    'organizer_tenant_id' => $rental->organizer_tenant_id,
                    'public_id' => (string) Str::ulid(),
                    'rental_request_id' => $rental->id,
                    'statement_number' => $statementNumber,
                    'revision' => 1,
                    'supersedes_statement_id' => null,
                    'status' => 'issued',
                    'dispute_status' => 'none',
                    'rental_outcome' => $rental->status,
                    'venue_timezone' => $rental->venue_timezone,
                    'agreed_start_at' => $rental->requested_start_at,
                    'agreed_end_at' => $rental->requested_end_at,
                    'currency' => $rental->currency,
                    'agreed_total_minor' => (int) $rental->total_minor,
                    'issued_at' => $now,
                    'generated_by' => 'system',
                    'created_at' => $now,
                ]);

                foreach ($rental->assets as $line) {
                    SettlementStatementLine::query()->forceCreate([
                        'tenant_id' => $ownerTenantId,
                        'organizer_tenant_id' => $rental->organizer_tenant_id,
                        'settlement_statement_id' => $statement->id,
                        'rental_asset_id' => $line->id,
                        'publication_public_id' => $line->publication_public_id,
                        'publication_version' => (int) $line->publication_version,
                        'asset_public_id' => $line->asset_public_id,
                        'asset_type' => $line->asset_type,
                        'name_en' => $line->name_en,
                        'name_ar' => $line->name_ar,
                        'pricing_model' => $line->pricing_model,
                        'unit_price_minor' => (int) $line->unit_price_minor,
                        'billable_units' => (int) $line->billable_units,
                        'line_total_minor' => (int) $line->line_total_minor,
                        'currency' => $line->currency,
                        'created_at' => $now,
                    ]);
                }

                return $statement->load('lines');
            },
            fn (SettlementStatement $statement) => $this->writeAudit($statement, $correlationId),
        );
    }

    private function writeAudit(SettlementStatement $statement, string $correlationId): void
    {
        foreach (['owner', 'organizer'] as $scope) {
            $this->audit->write(new MarketplaceAuditEvent(
                'statement.generated',
                $scope,
                'succeeded',
                $correlationId,
                $statement->public_id,
                [
                    'revision' => (int) $statement->revision,
                    'rental_outcome' => $statement->rental_outcome,
                    'currency' => $statement->currency,
                    'agreed_total_minor' => (int) $statement->agreed_total_minor,
                ],
                ownerTenantId: (int) $statement->tenant_id,
                organizerTenantId: (int) $statement->organizer_tenant_id,
            ));
        }
    }
}
