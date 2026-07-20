<?php

namespace App\Modules\VenueMarketplace\Application\Queries;

use App\Modules\Shared\Application\Pagination\CursorPage;
use App\Modules\Shared\Application\Pagination\CursorPaginator;
use App\Modules\VenueMarketplace\Domain\Exceptions\MarketplaceDomainException;
use App\Modules\VenueMarketplace\Http\Problems\Phase6Problem;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\Venue;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\VenueAsset;

final readonly class OwnerVenueAssetQuery
{
    public function __construct(private CursorPaginator $paginator) {}

    public function list(
        int $tenantId,
        string $venuePublicId,
        ?string $cursor = null,
        int $pageSize = 25,
    ): CursorPage {
        $venue = $this->venue($tenantId, $venuePublicId);
        $query = VenueAsset::query()->forTenantVenue($tenantId, $venue->id)
            ->with($this->relations());

        return $this->paginator->paginate(
            $query,
            "marketplace.owner.venue-assets.{$tenantId}.{$venue->id}",
            [],
            $cursor,
            $pageSize,
        );
    }

    public function get(int $tenantId, string $venuePublicId, string $assetPublicId): VenueAsset
    {
        $venue = $this->venue($tenantId, $venuePublicId);
        $asset = VenueAsset::query()->forTenantVenue($tenantId, $venue->id)
            ->where('venue_assets.public_id', $assetPublicId)
            ->with($this->relations())
            ->first();

        return $asset
            ?? throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_ASSET_NOT_FOUND);
    }

    private function venue(int $tenantId, string $publicId): Venue
    {
        return Venue::query()->forTenantPublicId($tenantId, $publicId)->first()
            ?? throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_VENUE_NOT_FOUND);
    }

    private function relations(): array
    {
        return [
            'availabilityWindows' => fn ($query) => $query->orderBy('available_from')->orderBy('id'),
            'binding',
            'publications' => fn ($query) => $query
                ->orderByDesc('publication_version'),
        ];
    }
}
