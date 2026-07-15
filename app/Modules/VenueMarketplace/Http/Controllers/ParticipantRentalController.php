<?php

namespace App\Modules\VenueMarketplace\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Shared\Domain\Context\RequestContextStore;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use App\Modules\VenueMarketplace\Application\Actions\ApproveRentalAction;
use App\Modules\VenueMarketplace\Application\Actions\CancelRentalAction;
use App\Modules\VenueMarketplace\Application\Actions\RejectRentalAction;
use App\Modules\VenueMarketplace\Application\Actions\RevokeRentalAction;
use App\Modules\VenueMarketplace\Application\Actions\SubmitRentalRequestAction;
use App\Modules\VenueMarketplace\Application\Queries\GetParticipantRentalQuery;
use App\Modules\VenueMarketplace\Application\Queries\ListParticipantRentalsQuery;
use App\Modules\VenueMarketplace\Application\Queries\RentalParticipantScope;
use App\Modules\VenueMarketplace\Http\Requests\ApproveRentalRequest;
use App\Modules\VenueMarketplace\Http\Requests\CancelRentalRequest;
use App\Modules\VenueMarketplace\Http\Requests\RejectRentalRequest;
use App\Modules\VenueMarketplace\Http\Requests\RevokeRentalRequest;
use App\Modules\VenueMarketplace\Http\Requests\SubmitRentalRequest;
use App\Modules\VenueMarketplace\Http\Resources\ParticipantRentalResource;
use Illuminate\Http\Request;

final class ParticipantRentalController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly TenantContextStore $tenants,
        private readonly RequestContextStore $requests,
        private readonly RentalParticipantScope $participants,
    ) {}

    public function index(Request $request, ListParticipantRentalsQuery $query)
    {
        $tenantId = (int) $this->tenants->current()->tenant->id;
        $request->validate([
            'viewer_role' => ['nullable', 'in:owner,organizer'],
            'status' => ['nullable', 'in:requested,approved,rejected,active,completed,cancelled,revoked'],
            'dispute_status' => ['nullable', 'in:none,open,under_review,resolved'],
            'cursor' => ['nullable', 'string', 'max:2048'],
            'page_size' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $page = $query->execute(
            $tenantId,
            $request->string('viewer_role')->toString() ?: null,
            $request->string('status')->toString() ?: null,
            $request->string('dispute_status')->toString() ?: null,
            $request->string('cursor')->toString() ?: null,
            $request->integer('page_size', 25),
        );
        foreach ($page->items as $rental) {
            $rental->setAttribute('viewer_role', $this->participants->role($tenantId, $rental));
        }

        return $this->success(
            ParticipantRentalResource::collection($page->items)->resolve($request),
            meta: ['page_size' => $page->pageSize, 'has_more' => $page->hasMore, 'next_cursor' => $page->nextCursor],
        );
    }

    public function store(SubmitRentalRequest $request, SubmitRentalRequestAction $action)
    {
        $context = $this->tenants->current();
        $rental = $action->execute(
            (int) $context->tenant->id,
            (int) $context->actor->id,
            (int) $request->validated('event_id'),
            $request->publicationPublicIds(),
            $request->validated('requested_start_at'),
            $request->validated('requested_end_at'),
            $request->validated('quote_digest'),
            (int) $request->validated('quote_version'),
            $request->idempotencyKey(),
            $this->requests->current()?->correlationId->value ?? 'marketplace-request',
        );
        $rental->setAttribute('viewer_role', 'organizer');

        return $this->success((new ParticipantRentalResource($rental))->resolve($request), 201);
    }

    public function show(Request $request, string $rental_public_id, GetParticipantRentalQuery $query)
    {
        $tenantId = (int) $this->tenants->current()->tenant->id;
        $rental = $query->execute($tenantId, $rental_public_id);
        $rental->setAttribute('viewer_role', $this->participants->role($tenantId, $rental));

        return $this->success((new ParticipantRentalResource($rental))->resolve($request));
    }

    public function approve(ApproveRentalRequest $request, string $rental_public_id, ApproveRentalAction $action)
    {
        $context = $this->tenants->current();
        $rental = $action->execute(
            (int) $context->tenant->id,
            (int) $context->actor->id,
            $rental_public_id,
            $request->expectedVersion(),
            $request->idempotencyKey(),
            $this->requests->current()?->correlationId->value ?? 'marketplace-approve',
        );
        $rental->setAttribute('viewer_role', 'owner');

        return $this->success((new ParticipantRentalResource($rental))->resolve($request));
    }

    public function reject(RejectRentalRequest $request, string $rental_public_id, RejectRentalAction $action)
    {
        $context = $this->tenants->current();
        $rental = $action->execute(
            (int) $context->tenant->id,
            (int) $context->actor->id,
            $rental_public_id,
            $request->reason(),
            $request->expectedVersion(),
            $this->requests->current()?->correlationId->value ?? 'marketplace-reject',
        );
        $rental->setAttribute('viewer_role', 'owner');

        return $this->success((new ParticipantRentalResource($rental))->resolve($request));
    }

    public function cancel(CancelRentalRequest $request, string $rental_public_id, CancelRentalAction $action)
    {
        $context = $this->tenants->current();
        $rental = $action->execute(
            (int) $context->tenant->id,
            (int) $context->actor->id,
            $rental_public_id,
            $request->expectedVersion(),
            $this->requests->current()?->correlationId->value ?? 'marketplace-cancel',
            $request->reason(),
        );
        $rental->setAttribute('viewer_role', 'organizer');

        return $this->success((new ParticipantRentalResource($rental))->resolve($request));
    }

    public function revoke(RevokeRentalRequest $request, string $rental_public_id, RevokeRentalAction $action)
    {
        $context = $this->tenants->current();
        $rental = $action->execute(
            (int) $context->tenant->id,
            (int) $context->actor->id,
            $rental_public_id,
            $request->reason(),
            $request->expectedVersion(),
            $this->requests->current()?->correlationId->value ?? 'marketplace-revoke',
        );
        $rental->setAttribute('viewer_role', 'owner');

        return $this->success((new ParticipantRentalResource($rental))->resolve($request));
    }
}
