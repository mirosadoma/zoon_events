<?php

namespace App\Modules\VenueMarketplace\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenancy\Application\Contracts\OrganizationEligibility;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use App\Modules\VenueMarketplace\Application\Actions\ArchiveVenueAction;
use App\Modules\VenueMarketplace\Application\Actions\ChangeVenueStatusAction;
use App\Modules\VenueMarketplace\Application\Actions\CreateVenueAction;
use App\Modules\VenueMarketplace\Application\Actions\UpdateVenueAction;
use App\Modules\VenueMarketplace\Application\Queries\OwnerVenueQuery;
use App\Modules\VenueMarketplace\Domain\Exceptions\MarketplaceDomainException;
use App\Modules\VenueMarketplace\Http\Problems\Phase6Problem;
use App\Modules\VenueMarketplace\Http\Requests\ChangeVenueStatusRequest;
use App\Modules\VenueMarketplace\Http\Requests\CreateVenueRequest;
use App\Modules\VenueMarketplace\Http\Requests\UpdateVenueRequest;
use App\Modules\VenueMarketplace\Http\Resources\OwnerVenueResource;
use App\Modules\Shared\Domain\Context\RequestContextStore;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use Illuminate\Http\Request;

final class OwnerVenueController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly TenantContextStore $tenants,
        private readonly RequestContextStore $requests,
        private readonly OrganizationEligibility $eligibility,
    ) {}

    public function index(Request $request, OwnerVenueQuery $query)
    {
        $context = $this->ownerContext();
        $page = $query->list(
            (int) $context->tenant->id,
            $request->string('status')->toString() ?: null,
            $request->string('cursor')->toString() ?: null,
            $request->integer('page_size', 25),
        );

        return $this->success(
            OwnerVenueResource::collection($page->items)->resolve($request),
            meta: ['page_size' => $page->pageSize, 'has_more' => $page->hasMore, 'next_cursor' => $page->nextCursor],
        );
    }

    public function store(CreateVenueRequest $request, CreateVenueAction $action)
    {
        $context = $this->ownerContext();
        $venue = $action->execute(
            (int) $context->tenant->id,
            (int) $context->actor->id,
            $request->attributesForAction(),
            $this->correlationId(),
        );

        return $this->success((new OwnerVenueResource($venue))->resolve($request), 201);
    }

    public function show(Request $request, string $venue_public_id, OwnerVenueQuery $query)
    {
        $context = $this->ownerContext();

        return $this->success((new OwnerVenueResource(
            $query->get((int) $context->tenant->id, $venue_public_id),
        ))->resolve($request));
    }

    public function update(
        UpdateVenueRequest $request,
        string $venue_public_id,
        UpdateVenueAction $action,
    ) {
        $context = $this->ownerContext();
        $venue = $action->execute(
            (int) $context->tenant->id,
            (int) $context->actor->id,
            $venue_public_id,
            $request->expectedVersion(),
            $request->attributesForAction(),
            $this->correlationId(),
        );

        return $this->success((new OwnerVenueResource($venue))->resolve($request));
    }

    public function changeStatus(
        ChangeVenueStatusRequest $request,
        string $venue_public_id,
        ChangeVenueStatusAction $action,
    ) {
        $context = $this->ownerContext();
        $venue = $action->execute(
            (int) $context->tenant->id,
            (int) $context->actor->id,
            $venue_public_id,
            $request->validated('status'),
            $this->correlationId(),
        );

        return $this->success((new OwnerVenueResource($venue))->resolve($request));
    }

    public function archive(Request $request, string $venue_public_id, ArchiveVenueAction $action)
    {
        $request->validate(['reason' => ['nullable', 'string', 'max:2000']]);
        $context = $this->ownerContext();
        $venue = $action->execute(
            (int) $context->tenant->id,
            (int) $context->actor->id,
            $venue_public_id,
            $this->correlationId(),
        );

        return $this->success((new OwnerVenueResource($venue))->resolve($request));
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
