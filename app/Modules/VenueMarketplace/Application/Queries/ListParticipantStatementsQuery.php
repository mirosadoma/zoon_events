<?php

namespace App\Modules\VenueMarketplace\Application\Queries;

use App\Modules\Shared\Application\Pagination\CursorPage;
use App\Modules\Shared\Application\Pagination\CursorPaginator;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\SettlementStatement;

final readonly class ListParticipantStatementsQuery
{
    public function __construct(private CursorPaginator $paginator) {}

    public function execute(
        int $actorTenantId,
        ?string $disputeStatus = null,
        ?string $cursor = null,
        int $pageSize = 25,
    ): CursorPage {
        $query = SettlementStatement::query()
            ->forParticipant($actorTenantId)
            ->with('lines');

        if ($disputeStatus !== null) {
            $query->where('dispute_status', $disputeStatus);
        }

        return $this->paginator->paginate(
            $query,
            "marketplace.participant.statements.{$actorTenantId}",
            compact('disputeStatus'),
            $cursor,
            $pageSize,
        );
    }
}
