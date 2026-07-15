<?php

namespace App\Modules\VenueMarketplace\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\Tenancy\Application\Contracts\OrganizationEligibility;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use App\Modules\VenueMarketplace\Application\Queries\MarketplaceCatalogReader;
use App\Modules\VenueMarketplace\Application\Services\RentalEventSnapshotResolver;
use App\Modules\VenueMarketplace\Domain\Exceptions\MarketplaceDomainException;
use App\Modules\VenueMarketplace\Domain\Services\MarketplaceQuoteService;
use App\Modules\VenueMarketplace\Domain\ValueObjects\RentalWindow;
use App\Modules\VenueMarketplace\Http\Problems\Phase6Problem;
use App\Modules\VenueMarketplace\Http\Requests\MarketplaceQuoteRequest;
use App\Modules\VenueMarketplace\Http\Resources\MarketplaceQuoteResource;
use Carbon\CarbonImmutable;

final class MarketplaceQuoteController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly TenantContextStore $tenants,
        private readonly OrganizationEligibility $eligibility,
    ) {}

    public function store(
        MarketplaceQuoteRequest $request,
        MarketplaceCatalogReader $catalog,
        RentalEventSnapshotResolver $events,
        MarketplaceQuoteService $quotes,
    ) {
        $context = $this->tenants->current();
        $tenantId = (int) $context->tenant->id;
        if (! $this->eligibility->check($tenantId, OrganizationEligibility::REQUEST_RENTALS)->eligible) {
            throw new MarketplaceDomainException(Phase6Problem::ORGANIZATION_TYPE_NOT_ELIGIBLE);
        }
        $events->resolve($tenantId, (int) $request->validated('event_id'));
        $start = CarbonImmutable::parse($request->validated('requested_start_at'))->utc();
        $end = CarbonImmutable::parse($request->validated('requested_end_at'))->utc();
        $publications = $catalog->getAvailable(
            $request->publicationPublicIds(),
            $start->toISOString(),
            $end->toISOString(),
        );
        try {
            $quote = $quotes->calculate(
                $publications,
                new RentalWindow($start->toDateTimeImmutable(), $end->toDateTimeImmutable()),
            );
        } catch (\InvalidArgumentException $exception) {
            $reason = str_contains($exception->getMessage(), 'venue')
                ? Phase6Problem::MARKETPLACE_MIXED_VENUE
                : Phase6Problem::MARKETPLACE_MIXED_CURRENCY;
            throw new MarketplaceDomainException($reason);
        }

        return $this->success((new MarketplaceQuoteResource($quote))->resolve($request));
    }
}
