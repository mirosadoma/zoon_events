<?php

namespace App\Modules\AdminConsole\ViewModels\Marketplace;

use App\Modules\VenueMarketplace\Application\Queries\OwnerVenueQuery;
use App\Modules\VenueMarketplace\Http\Resources\OwnerVenueResource;

final readonly class TenantVenueIndexViewModel
{
    public function __construct(private OwnerVenueQuery $venueQuery) {}

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string, bool>  $permissions
     * @return array<string, mixed>
     */
    public function index(string $tenantId, array $filters = [], array $permissions = []): array
    {
        $page = $this->venueQuery->list(
            (int) $tenantId,
            ($filters['status'] ?? null) ?: null,
            ($filters['cursor'] ?? null) ?: null,
            15,
        );

        return [
            'tenantId' => $tenantId,
            'venues' => OwnerVenueResource::collection($page->items)->resolve(request()),
            'filters' => $this->normalizeFilters($filters),
            'pagination' => [
                'per_page' => $page->pageSize,
                'has_more' => $page->hasMore,
                'next_cursor' => $page->nextCursor,
            ],
            'actions' => $this->venuePermissions($permissions),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function normalizeFilters(array $filters): array
    {
        return [
            'status' => $filters['status'] ?? null,
            'country_id' => $filters['country_id'] ?? null,
            'city_id' => $filters['city_id'] ?? null,
            'publication_readiness' => $filters['publication_readiness'] ?? null,
            'cursor' => $filters['cursor'] ?? null,
        ];
    }

    /**
     * @param  array<string, bool>  $overrides
     * @return array<string, bool>
     */
    private function venuePermissions(array $overrides): array
    {
        $defaults = [
            'canCreate' => false,
            'canUpdate' => false,
            'canArchive' => false,
            'canChangeStatus' => false,
            'canManageAssets' => false,
            'canPublish' => false,
        ];

        return array_merge($defaults, $overrides);
    }
}
