<?php

namespace App\Modules\VenueMarketplace\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\Tenancy\Application\Contracts\OrganizationEligibility;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use App\Modules\VenueMarketplace\Application\Queries\MarketplaceCatalogReader;
use App\Modules\VenueMarketplace\Application\Services\MarketplaceCatalogCache;
use App\Modules\VenueMarketplace\Domain\Exceptions\MarketplaceDomainException;
use App\Modules\VenueMarketplace\Http\Problems\Phase6Problem;
use App\Modules\VenueMarketplace\Http\Requests\CatalogSearchRequest;
use App\Modules\VenueMarketplace\Http\Resources\MarketplaceCatalogResource;
use Illuminate\Http\Request;

final class MarketplaceCatalogController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly TenantContextStore $tenants,
        private readonly OrganizationEligibility $eligibility,
        private readonly MarketplaceCatalogCache $cache,
    ) {}

    public function index(CatalogSearchRequest $request, MarketplaceCatalogReader $reader)
    {
        $tenantId = $this->organizerTenantId();
        $filters = $request->filters();
        $payload = $this->cache->remember(
            $tenantId,
            app()->getLocale(),
            $filters,
            $request->validated('cursor'),
            function () use ($reader, $filters, $request): array {
                $page = $reader->search(
                    $filters,
                    $request->validated('cursor'),
                    (int) ($request->validated('page_size') ?? 25),
                );

                return [
                    'data' => MarketplaceCatalogResource::collection($page->items)->resolve($request),
                    'meta' => [
                        'page_size' => $page->pageSize,
                        'has_more' => $page->hasMore,
                        'next_cursor' => $page->nextCursor,
                    ],
                ];
            },
        );

        return $this->success(
            $payload['data'],
            meta: $payload['meta'],
        );
    }

    public function show(Request $request, string $publication_public_id, MarketplaceCatalogReader $reader)
    {
        $this->organizerTenantId();

        return $this->success(
            (new MarketplaceCatalogResource($reader->get($publication_public_id)))->resolve($request),
        );
    }

    private function organizerTenantId(): int
    {
        $tenantId = (int) $this->tenants->current()->tenant->id;
        if (! $this->eligibility->check($tenantId, OrganizationEligibility::REQUEST_RENTALS)->eligible) {
            throw new MarketplaceDomainException(Phase6Problem::ORGANIZATION_TYPE_NOT_ELIGIBLE);
        }

        return $tenantId;
    }
}
