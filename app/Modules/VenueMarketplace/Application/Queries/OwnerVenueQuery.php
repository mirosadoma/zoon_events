<?php

namespace App\Modules\VenueMarketplace\Application\Queries;

use App\Modules\Shared\Application\Pagination\CursorPage;
use App\Modules\Shared\Application\Pagination\CursorPaginator;
use App\Modules\VenueMarketplace\Domain\Exceptions\MarketplaceDomainException;
use App\Modules\VenueMarketplace\Http\Problems\Phase6Problem;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\Venue;

final readonly class OwnerVenueQuery
{
    public function __construct(private CursorPaginator $paginator) {}

    public function list(
        int $tenantId,
        ?string $status = null,
        ?string $cursor = null,
        int $pageSize = 25,
    ): CursorPage {
        $query = Venue::query()->forTenant((string) $tenantId)
            ->withCount([
                'assets',
                'assets as published_asset_count' => fn ($assets) => $assets
                    ->whereHas('publications', fn ($publications) => $publications->where('status', 'active')),
            ]);

        if ($status !== null) {
            $query->where('venues.status', $status);
        }

        return $this->paginator->paginate(
            $query,
            "marketplace.owner.venues.{$tenantId}",
            ['status' => $status],
            $cursor,
            $pageSize,
        );
    }

    public function get(int $tenantId, string $publicId): Venue
    {
        $venue = Venue::query()->forTenantPublicId($tenantId, $publicId)
            ->withCount([
                'assets',
                'assets as published_asset_count' => fn ($assets) => $assets
                    ->whereHas('publications', fn ($publications) => $publications->where('status', 'active')),
            ])
            ->first();

        return $venue
            ?? throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_VENUE_NOT_FOUND);
    }
}
