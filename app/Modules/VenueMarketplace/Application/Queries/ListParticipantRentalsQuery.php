<?php

namespace App\Modules\VenueMarketplace\Application\Queries;

use App\Modules\Shared\Application\Pagination\CursorPage;
use App\Modules\Shared\Application\Pagination\CursorPaginator;

final readonly class ListParticipantRentalsQuery
{
    public function __construct(
        private RentalParticipantScope $scope,
        private CursorPaginator $paginator,
    ) {}

    public function execute(
        int $actorTenantId,
        ?string $viewerRole = null,
        ?string $status = null,
        ?string $disputeStatus = null,
        ?string $cursor = null,
        int $pageSize = 25,
    ): CursorPage {
        $query = $this->scope->query($actorTenantId, $viewerRole)->with('assets');
        if ($status !== null) {
            $query->where('status', $status);
        }
        if ($disputeStatus !== null) {
            $query->where('dispute_status', $disputeStatus);
        }

        return $this->paginator->paginate(
            $query,
            "marketplace.participant.rentals.{$actorTenantId}",
            compact('viewerRole', 'status', 'disputeStatus'),
            $cursor,
            $pageSize,
        );
    }
}
