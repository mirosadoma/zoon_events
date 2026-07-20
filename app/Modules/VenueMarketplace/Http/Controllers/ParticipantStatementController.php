<?php

namespace App\Modules\VenueMarketplace\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Shared\Domain\Context\RequestContextStore;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use App\Modules\VenueMarketplace\Application\Actions\OpenMarketplaceDisputeAction;
use App\Modules\VenueMarketplace\Application\Exports\StreamSettlementStatementCsv;
use App\Modules\VenueMarketplace\Application\Queries\GetParticipantStatementQuery;
use App\Modules\VenueMarketplace\Application\Queries\ListParticipantStatementsQuery;
use App\Modules\VenueMarketplace\Domain\Exceptions\MarketplaceDomainException;
use App\Modules\VenueMarketplace\Http\Problems\Phase6Problem;
use App\Modules\VenueMarketplace\Http\Requests\OpenDisputeRequest;
use App\Modules\VenueMarketplace\Http\Resources\ParticipantDisputeResource;
use App\Modules\VenueMarketplace\Http\Resources\ParticipantStatementResource;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\MarketplaceDispute;
use Illuminate\Http\Request;

final class ParticipantStatementController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly TenantContextStore $tenants,
        private readonly RequestContextStore $requests,
    ) {}

    public function index(Request $request, ListParticipantStatementsQuery $query)
    {
        $tenantId = (int) $this->tenants->current()->tenant->id;
        $request->validate([
            'dispute_status' => ['nullable', 'in:none,open,under_review,resolved'],
            'cursor' => ['nullable', 'string', 'max:2048'],
            'page_size' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $page = $query->execute(
            $tenantId,
            $request->string('dispute_status')->toString() ?: null,
            $request->string('cursor')->toString() ?: null,
            $request->integer('page_size', 25),
        );

        return $this->success(
            ParticipantStatementResource::collection($page->items)->resolve($request),
            meta: ['page_size' => $page->pageSize, 'has_more' => $page->hasMore, 'next_cursor' => $page->nextCursor],
        );
    }

    public function show(Request $request, string $statement_public_id, GetParticipantStatementQuery $query)
    {
        $tenantId = (int) $this->tenants->current()->tenant->id;
        $statement = $query->execute($tenantId, $statement_public_id);

        return $this->success((new ParticipantStatementResource($statement))->resolve($request));
    }

    public function export(
        Request $request,
        string $statement_public_id,
        StreamSettlementStatementCsv $exporter,
    ) {
        $context = $this->tenants->current();

        return $exporter->execute(
            (int) $context->tenant->id,
            (int) $context->actor->id,
            $statement_public_id,
            $this->requests->current()?->correlationId->value ?? 'marketplace-export',
            $request->getPreferredLanguage(['en', 'ar']) ?? 'en',
        );
    }

    public function openDispute(
        OpenDisputeRequest $request,
        string $statement_public_id,
        OpenMarketplaceDisputeAction $action,
    ) {
        $context = $this->tenants->current();
        $dispute = $action->execute(
            (int) $context->tenant->id,
            (int) $context->actor->id,
            $statement_public_id,
            $request->reasonCode(),
            $request->reason(),
            $request->idempotencyKey(),
            $this->requests->current()?->correlationId->value ?? 'marketplace-dispute-open',
        );

        $dispute->load(['statement:id,public_id', 'rental:id,public_id']);

        return $this->success(
            (new ParticipantDisputeResource($dispute))->resolve($request),
            201,
        );
    }

    public function showDispute(Request $request, string $dispute_public_id)
    {
        $tenantId = (int) $this->tenants->current()->tenant->id;

        $dispute = MarketplaceDispute::query()
            ->forParticipant($tenantId)
            ->where('public_id', $dispute_public_id)
            ->with(['participantEvents', 'statement:id,public_id', 'rental:id,public_id'])
            ->first();

        if ($dispute === null) {
            throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_DISPUTE_NOT_FOUND);
        }

        $dispute->setRelation('events', $dispute->getRelation('participantEvents'));

        return $this->success((new ParticipantDisputeResource($dispute))->resolve($request));
    }
}
