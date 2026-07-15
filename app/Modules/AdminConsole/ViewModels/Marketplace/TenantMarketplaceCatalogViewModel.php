<?php

namespace App\Modules\AdminConsole\ViewModels\Marketplace;

use App\Modules\VenueMarketplace\Application\Queries\MarketplaceCatalogReader;
use App\Modules\VenueMarketplace\Http\Resources\MarketplaceCatalogResource;

final readonly class TenantMarketplaceCatalogViewModel
{
    public function __construct(private MarketplaceCatalogReader $catalog) {}

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string, bool>  $permissions
     * @return array<string, mixed>
     */
    public function index(string $tenantId, array $filters = [], array $permissions = []): array
    {
        $queryFilters = $this->mapFilters($filters);
        $cursor = $queryFilters['cursor'] ?? null;
        unset($queryFilters['cursor']);

        $page = $this->catalog->search($queryFilters, $cursor, 15);

        return [
            'tenantId' => $tenantId,
            'catalog' => MarketplaceCatalogResource::collection($page->items)->resolve(request()),
            'filters' => $this->normalizeFilters($filters),
            'pagination' => [
                'per_page' => $page->pageSize,
                'has_more' => $page->hasMore,
                'next_cursor' => $page->nextCursor,
            ],
            'actions' => $this->catalogPermissions($permissions),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function mapFilters(array $filters): array
    {
        return array_filter([
            'venue_public_id' => $filters['venue_public_id'] ?? null,
            'country_code' => $filters['country_id'] ?? null,
            'city_code' => $filters['city_id'] ?? null,
            'asset_type' => $filters['asset_type'] ?? null,
            'capability' => $filters['capability'] ?? null,
            'minimum_capacity_per_minute' => $filters['minimum_capacity'] ?? null,
            'currency' => $filters['currency'] ?? null,
            'requested_start_at' => $filters['starts_at'] ?? null,
            'requested_end_at' => $filters['ends_at'] ?? null,
            'cursor' => $filters['cursor'] ?? null,
        ], static fn (mixed $v): bool => $v !== null && $v !== '');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function normalizeFilters(array $filters): array
    {
        return [
            'venue_public_id' => $filters['venue_public_id'] ?? null,
            'country_id' => $filters['country_id'] ?? null,
            'city_id' => $filters['city_id'] ?? null,
            'asset_type' => $filters['asset_type'] ?? null,
            'capability' => $filters['capability'] ?? null,
            'minimum_capacity' => $filters['minimum_capacity'] ?? null,
            'currency' => $filters['currency'] ?? null,
            'starts_at' => $filters['starts_at'] ?? null,
            'ends_at' => $filters['ends_at'] ?? null,
            'cursor' => $filters['cursor'] ?? null,
        ];
    }

    /**
     * @param  array<string, bool>  $overrides
     * @return array<string, bool>
     */
    private function catalogPermissions(array $overrides): array
    {
        $defaults = [
            'canQuote' => false,
            'canRequest' => false,
        ];

        return array_merge($defaults, $overrides);
    }
}
