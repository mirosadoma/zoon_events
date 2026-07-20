<?php

namespace App\Modules\AdminConsole\ViewModels\Marketplace;

use App\Modules\VenueMarketplace\Application\Queries\OwnerVenueAssetQuery;
use App\Modules\VenueMarketplace\Application\Queries\OwnerVenueQuery;
use App\Modules\VenueMarketplace\Http\Resources\OwnerVenueAssetResource;
use App\Modules\VenueMarketplace\Http\Resources\OwnerVenueResource;

final readonly class TenantVenueDetailViewModel
{
    public function __construct(
        private OwnerVenueQuery $venueQuery,
        private OwnerVenueAssetQuery $assetQuery,
    ) {}

    /**
     * @param  array<string, bool>  $permissions
     * @return array<string, mixed>
     */
    public function show(string $tenantId, string $venuePublicId, array $permissions = []): array
    {
        $venue = $this->venueQuery->get((int) $tenantId, $venuePublicId);
        $assetPage = $this->assetQuery->list((int) $tenantId, $venuePublicId);

        return [
            'tenantId' => $tenantId,
            'venuePublicId' => $venuePublicId,
            'venue' => (new OwnerVenueResource($venue))->resolve(request()),
            'assets' => OwnerVenueAssetResource::collection($assetPage->items)->resolve(request()),
            'actions' => $this->venuePermissions($permissions),
        ];
    }

    /**
     * @param  array<string, bool>  $overrides
     * @return array<string, bool>
     */
    private function venuePermissions(array $overrides): array
    {
        $defaults = [
            'canUpdate' => false,
            'canArchive' => false,
            'canChangeStatus' => false,
            'canManageAssets' => false,
            'canPublish' => false,
            'canWithdraw' => false,
        ];

        return array_merge($defaults, $overrides);
    }
}
