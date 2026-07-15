<?php

namespace App\Modules\VenueMarketplace\Application\Queries;

use App\Modules\VenueMarketplace\Domain\Exceptions\MarketplaceDomainException;
use App\Modules\VenueMarketplace\Http\Problems\Phase6Problem;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\SettlementStatement;

final readonly class GetParticipantStatementQuery
{
    public function execute(int $actorTenantId, string $statementPublicId): SettlementStatement
    {
        return SettlementStatement::query()
            ->forParticipantPublicId($actorTenantId, $statementPublicId)
            ->with(['lines', 'supersedes:id,public_id'])
            ->first()
            ?? throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_STATEMENT_NOT_FOUND);
    }
}
