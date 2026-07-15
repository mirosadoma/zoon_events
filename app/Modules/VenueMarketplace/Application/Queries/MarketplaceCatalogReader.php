<?php

namespace App\Modules\VenueMarketplace\Application\Queries;

use App\Modules\Shared\Application\Pagination\CursorPage;
use App\Modules\Shared\Application\Pagination\CursorPaginator;
use App\Modules\VenueMarketplace\Domain\Exceptions\MarketplaceDomainException;
use App\Modules\VenueMarketplace\Http\Problems\Phase6Problem;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\MarketplaceCatalogPublication;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final readonly class MarketplaceCatalogReader
{
    private const CATALOG_COLUMNS = [
        'id', 'tenant_id', 'venue_asset_id', 'public_id', 'venue_public_id', 'asset_public_id',
        'publication_version', 'venue_name_en', 'venue_name_ar', 'venue_description_en',
        'venue_description_ar', 'asset_name_en', 'asset_name_ar', 'asset_description_en',
        'asset_description_ar', 'address_en', 'address_ar', 'country_code', 'city_code',
        'timezone', 'asset_type', 'location_en', 'location_ar', 'capacity_per_minute',
        'pricing_model', 'price_minor', 'currency', 'availability_windows', 'public_contact',
        'status', 'published_at', 'withdrawn_at', 'created_at',
    ];

    public function __construct(private CursorPaginator $paginator) {}

    /** @param array<string, mixed> $filters */
    public function search(array $filters, ?string $cursor = null, int $pageSize = 25): CursorPage
    {
        $query = MarketplaceCatalogPublication::query()
            ->withoutGlobalScopes()
            ->select(self::CATALOG_COLUMNS)
            ->where('marketplace_catalog_publications.status', 'active')
            ->with(['capabilities', 'availabilityWindows']);

        $this->applyFilters($query, $filters);
        $page = $this->paginator->paginate(
            $query,
            'marketplace.catalog',
            $this->normalizedFilters($filters),
            $cursor,
            $pageSize,
            'published_at',
        );
        $window = $this->requestedWindow($filters);
        foreach ($page->items as $publication) {
            $publication->setAttribute(
                'available_for_requested_window',
                $window === null ? null : $this->containsWindow($publication, ...$window),
            );
        }

        if ($window === null) {
            return $page;
        }
        $items = array_values(array_filter(
            $page->items,
            fn (MarketplaceCatalogPublication $publication): bool => $publication->available_for_requested_window === true,
        ));

        return new CursorPage($items, $page->nextCursor, $page->hasMore, count($items));
    }

    public function get(string $publicationPublicId): MarketplaceCatalogPublication
    {
        return MarketplaceCatalogPublication::query()
            ->withoutGlobalScopes()
            ->select(self::CATALOG_COLUMNS)
            ->where('status', 'active')
            ->where('public_id', $publicationPublicId)
            ->with(['capabilities', 'availabilityWindows'])
            ->first()
            ?? throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_ASSET_NOT_FOUND);
    }

    /** @param list<string> $publicationPublicIds */
    public function getAvailable(
        array $publicationPublicIds,
        string $requestedStartAt,
        string $requestedEndAt,
    ): Collection {
        $ids = array_values(array_unique($publicationPublicIds));
        sort($ids, SORT_STRING);
        $publications = MarketplaceCatalogPublication::query()
            ->withoutGlobalScopes()
            ->select(self::CATALOG_COLUMNS)
            ->where('status', 'active')
            ->whereIn('public_id', $ids)
            ->with(['capabilities', 'availabilityWindows'])
            ->orderBy('public_id')
            ->get();
        if ($publications->count() !== count($ids)) {
            throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_ASSET_NOT_FOUND);
        }
        $start = CarbonImmutable::parse($requestedStartAt)->utc();
        $end = CarbonImmutable::parse($requestedEndAt)->utc();
        if ($end <= $start) {
            throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_WINDOW_INVALID);
        }
        foreach ($publications as $publication) {
            if (! $this->containsWindow($publication, $start, $end)) {
                throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_ASSET_UNAVAILABLE);
            }
        }

        return $publications;
    }

    /** @param array<string, mixed> $filters */
    private function applyFilters(Builder $query, array $filters): void
    {
        foreach ([
            'venue_public_id', 'country_code', 'city_code', 'asset_type', 'currency',
        ] as $field) {
            if (($filters[$field] ?? null) !== null && $filters[$field] !== '') {
                $query->where("marketplace_catalog_publications.{$field}", $filters[$field]);
            }
        }
        if (isset($filters['minimum_capacity_per_minute'])) {
            $query->where('capacity_per_minute', '>=', (int) $filters['minimum_capacity_per_minute']);
        }
        if (isset($filters['maximum_price_minor'])) {
            $query->where('price_minor', '<=', (int) $filters['maximum_price_minor']);
        }
        if (($filters['capability'] ?? null) !== null && $filters['capability'] !== '') {
            $query->whereHas(
                'capabilities',
                fn (Builder $capability): Builder => $capability
                    ->where('capability_code', $filters['capability']),
            );
        }
        if (isset($filters['requested_start_at'], $filters['requested_end_at'])) {
            $start = CarbonImmutable::parse((string) $filters['requested_start_at'])->utc();
            $end = CarbonImmutable::parse((string) $filters['requested_end_at'])->utc();
            $query->whereHas(
                'availabilityWindows',
                fn (Builder $availability): Builder => $availability
                    ->where('available_from', '<=', $start)
                    ->where('available_until', '>=', $end),
            );
        }
        if (isset($filters['requested_start_at'], $filters['requested_end_at'])
            && Schema::hasTable('asset_reservations')) {
            $start = CarbonImmutable::parse((string) $filters['requested_start_at'])->utc();
            $end = CarbonImmutable::parse((string) $filters['requested_end_at'])->utc();
            $query->whereNotExists(function ($reservation) use ($start, $end): void {
                $reservation->select(DB::raw(1))
                    ->from('asset_reservations')
                    ->whereColumn(
                        'asset_reservations.tenant_id',
                        'marketplace_catalog_publications.tenant_id',
                    )
                    ->whereColumn(
                        'asset_reservations.venue_asset_id',
                        'marketplace_catalog_publications.venue_asset_id',
                    )
                    ->whereIn('asset_reservations.status', ['reserved', 'active'])
                    ->where('asset_reservations.reserved_from', '<', $end)
                    ->where('asset_reservations.reserved_until', '>', $start);
            });
        }
    }

    /** @param array<string, mixed> $filters */
    private function requestedWindow(array $filters): ?array
    {
        $startsAt = $filters['requested_start_at'] ?? null;
        $endsAt = $filters['requested_end_at'] ?? null;
        if ($startsAt === null && $endsAt === null) {
            return null;
        }
        if ($startsAt === null || $endsAt === null) {
            throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_WINDOW_INVALID);
        }

        $start = CarbonImmutable::parse((string) $startsAt)->utc();
        $end = CarbonImmutable::parse((string) $endsAt)->utc();
        if ($end <= $start) {
            throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_WINDOW_INVALID);
        }

        return [$start, $end];
    }

    private function containsWindow(
        MarketplaceCatalogPublication $publication,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): bool {
        if ($publication->relationLoaded('availabilityWindows')) {
            return $publication->availabilityWindows->contains(
                fn ($window): bool => $window->available_from <= $start
                    && $window->available_until >= $end,
            );
        }
        foreach ($publication->availability_windows ?? [] as $window) {
            if (CarbonImmutable::parse($window['starts_at'])->utc() <= $start
                && CarbonImmutable::parse($window['ends_at'])->utc() >= $end) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $filters */
    private function normalizedFilters(array $filters): array
    {
        ksort($filters);

        return array_filter($filters, static fn (mixed $value): bool => $value !== null && $value !== '');
    }
}
