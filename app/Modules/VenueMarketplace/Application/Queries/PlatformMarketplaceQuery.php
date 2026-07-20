<?php

namespace App\Modules\VenueMarketplace\Application\Queries;

use App\Modules\Shared\Application\Pagination\CursorPage;
use App\Modules\Shared\Application\Pagination\CursorPaginator;
use App\Modules\VenueMarketplace\Domain\Exceptions\MarketplaceDomainException;
use App\Modules\VenueMarketplace\Http\Problems\Phase6Problem;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\MarketplaceDispute;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\RentalRequest;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\SettlementStatement;

final readonly class PlatformMarketplaceQuery
{
    public function __construct(private CursorPaginator $paginator) {}

    public function listRentals(
        ?string $status = null,
        ?string $disputeStatus = null,
        ?string $cursor = null,
        int $pageSize = 25,
    ): CursorPage {
        $query = RentalRequest::query()->withoutGlobalScopes()->with('assets');

        if ($status !== null) {
            $query->where('status', $status);
        }
        if ($disputeStatus !== null) {
            $query->where('dispute_status', $disputeStatus);
        }

        return $this->paginator->paginate(
            $query,
            'marketplace.platform.rentals',
            compact('status', 'disputeStatus'),
            $cursor,
            $pageSize,
        );
    }

    public function listStatements(
        ?string $cursor = null,
        int $pageSize = 25,
    ): CursorPage {
        $query = SettlementStatement::query()
            ->withoutGlobalScopes()
            ->with('lines');

        return $this->paginator->paginate(
            $query,
            'marketplace.platform.statements',
            [],
            $cursor,
            $pageSize,
        );
    }

    public function listDisputes(
        ?string $status = null,
        ?string $cursor = null,
        int $pageSize = 25,
    ): CursorPage {
        $query = MarketplaceDispute::query()
            ->forPlatform()
            ->with('events');

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $this->paginator->paginate(
            $query,
            'marketplace.platform.disputes',
            compact('status'),
            $cursor,
            $pageSize,
        );
    }

    public function getDispute(string $disputePublicId): MarketplaceDispute
    {
        return MarketplaceDispute::query()
            ->forPlatform()
            ->where('public_id', $disputePublicId)
            ->with('events')
            ->first()
            ?? throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_DISPUTE_NOT_FOUND);
    }
}
