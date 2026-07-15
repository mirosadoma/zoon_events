<?php

namespace App\Modules\VenueMarketplace\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Shared\Domain\Context\RequestContextStore;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\Tenancy\Application\Contracts\OrganizationEligibility;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use App\Modules\VenueMarketplace\Application\Actions\CreateVenueAssetAction;
use App\Modules\VenueMarketplace\Application\Actions\PublishVenueAssetAction;
use App\Modules\VenueMarketplace\Application\Actions\ReplaceAssetAvailabilityAction;
use App\Modules\VenueMarketplace\Application\Actions\UpdateVenueAssetAction;
use App\Modules\VenueMarketplace\Application\Actions\WithdrawVenueAssetPublicationAction;
use App\Modules\VenueMarketplace\Application\Queries\OwnerVenueAssetQuery;
use App\Modules\VenueMarketplace\Domain\Exceptions\MarketplaceDomainException;
use App\Modules\VenueMarketplace\Http\Problems\Phase6Problem;
use App\Modules\VenueMarketplace\Http\Requests\CreateVenueAssetRequest;
use App\Modules\VenueMarketplace\Http\Requests\PublishVenueAssetRequest;
use App\Modules\VenueMarketplace\Http\Requests\ReplaceAvailabilityRequest;
use App\Modules\VenueMarketplace\Http\Requests\UpdateVenueAssetRequest;
use App\Modules\VenueMarketplace\Http\Resources\AvailabilityWindowResource;
use App\Modules\VenueMarketplace\Http\Resources\OwnerVenueAssetResource;
use App\Modules\VenueMarketplace\Http\Resources\PublishedCatalogItemResource;
use Illuminate\Http\Request;

final class OwnerVenueAssetController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly TenantContextStore $tenants,
        private readonly RequestContextStore $requests,
        private readonly OrganizationEligibility $eligibility,
    ) {}

    public function index(
        Request $request,
        string $venue_public_id,
        OwnerVenueAssetQuery $query,
    ) {
        $context = $this->ownerContext();
        $page = $query->list(
            (int) $context->tenant->id,
            $venue_public_id,
            $request->string('cursor')->toString() ?: null,
            $request->integer('page_size', 25),
        );

        return $this->success(
            OwnerVenueAssetResource::collection($page->items)->resolve($request),
            meta: ['page_size' => $page->pageSize, 'has_more' => $page->hasMore, 'next_cursor' => $page->nextCursor],
        );
    }

    public function store(
        CreateVenueAssetRequest $request,
        string $venue_public_id,
        OwnerVenueAssetQuery $query,
        CreateVenueAssetAction $action,
    ) {
        $context = $this->ownerContext();
        $asset = $action->execute(
            (int) $context->tenant->id,
            (int) $context->actor->id,
            $venue_public_id,
            $request->attributesForAction(),
            $request->bindingForAction(),
            $this->correlationId(),
        );

        return $this->success((new OwnerVenueAssetResource(
            $query->get((int) $context->tenant->id, $venue_public_id, $asset->public_id),
        ))->resolve($request), 201);
    }

    public function show(
        Request $request,
        string $venue_public_id,
        string $asset_public_id,
        OwnerVenueAssetQuery $query,
    ) {
        $context = $this->ownerContext();

        return $this->success((new OwnerVenueAssetResource(
            $query->get((int) $context->tenant->id, $venue_public_id, $asset_public_id),
        ))->resolve($request));
    }

    public function update(
        UpdateVenueAssetRequest $request,
        string $venue_public_id,
        string $asset_public_id,
        OwnerVenueAssetQuery $query,
        UpdateVenueAssetAction $action,
    ) {
        $context = $this->ownerContext();
        $query->get((int) $context->tenant->id, $venue_public_id, $asset_public_id);
        $asset = $action->execute(
            (int) $context->tenant->id,
            (int) $context->actor->id,
            $asset_public_id,
            $request->expectedVersion(),
            $request->attributesForAction(),
            $request->bindingForAction(),
            $this->correlationId(),
        );

        return $this->success((new OwnerVenueAssetResource(
            $query->get((int) $context->tenant->id, $venue_public_id, $asset->public_id),
        ))->resolve($request));
    }

    public function replaceAvailability(
        ReplaceAvailabilityRequest $request,
        string $venue_public_id,
        string $asset_public_id,
        OwnerVenueAssetQuery $query,
        ReplaceAssetAvailabilityAction $action,
    ) {
        $context = $this->ownerContext();
        $query->get((int) $context->tenant->id, $venue_public_id, $asset_public_id);
        $windows = $action->execute(
            (int) $context->tenant->id,
            (int) $context->actor->id,
            $asset_public_id,
            $request->expectedVersion(),
            $request->windowsForAction(),
            $this->correlationId(),
        );

        return $this->success(AvailabilityWindowResource::collection($windows)->resolve($request));
    }

    public function publish(
        PublishVenueAssetRequest $request,
        string $venue_public_id,
        string $asset_public_id,
        OwnerVenueAssetQuery $query,
        PublishVenueAssetAction $action,
    ) {
        $context = $this->ownerContext();
        $query->get((int) $context->tenant->id, $venue_public_id, $asset_public_id);
        $publication = $action->execute(
            (int) $context->tenant->id,
            (int) $context->actor->id,
            $asset_public_id,
            $this->correlationId(),
        );

        return $this->success((new PublishedCatalogItemResource($publication))->resolve($request), 201);
    }

    public function withdraw(
        Request $request,
        string $venue_public_id,
        string $asset_public_id,
        OwnerVenueAssetQuery $query,
        WithdrawVenueAssetPublicationAction $action,
    ) {
        $context = $this->ownerContext();
        $query->get((int) $context->tenant->id, $venue_public_id, $asset_public_id);
        $action->execute(
            (int) $context->tenant->id,
            (int) $context->actor->id,
            $asset_public_id,
            $this->correlationId(),
        );

        return $this->empty();
    }

    private function ownerContext()
    {
        $context = $this->tenants->current();
        if (! $this->eligibility->check((int) $context->tenant->id, OrganizationEligibility::OWN_VENUES)->eligible) {
            throw new MarketplaceDomainException(Phase6Problem::ORGANIZATION_TYPE_NOT_ELIGIBLE);
        }

        return $context;
    }

    private function correlationId(): string
    {
        return $this->requests->current()?->correlationId->value ?? 'marketplace-request';
    }
}
