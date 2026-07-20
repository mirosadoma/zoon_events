<?php

namespace App\Modules\VenueMarketplace\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Shared\Domain\Context\RequestContextStore;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\VenueMarketplace\Application\Actions\AddMarketplaceDisputeNoteAction;
use App\Modules\VenueMarketplace\Application\Actions\ResolveMarketplaceDisputeAction;
use App\Modules\VenueMarketplace\Application\Actions\ReviseSettlementStatementAction;
use App\Modules\VenueMarketplace\Application\Actions\StartMarketplaceDisputeReviewAction;
use App\Modules\VenueMarketplace\Application\Queries\PlatformMarketplaceQuery;
use App\Modules\VenueMarketplace\Http\Requests\PlatformDisputeActionRequest;
use App\Modules\VenueMarketplace\Http\Requests\ReviseStatementRequest;
use App\Modules\VenueMarketplace\Http\Resources\ParticipantDisputeResource;
use App\Modules\VenueMarketplace\Http\Resources\ParticipantRentalResource;
use App\Modules\VenueMarketplace\Http\Resources\ParticipantStatementResource;
use Illuminate\Http\Request;

final class PlatformMarketplaceController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly RequestContextStore $requests,
    ) {}

    public function listRentals(Request $request, PlatformMarketplaceQuery $query)
    {
        $request->validate([
            'status' => ['nullable', 'in:requested,approved,rejected,active,completed,cancelled,revoked'],
            'dispute_status' => ['nullable', 'in:none,open,under_review,resolved'],
            'cursor' => ['nullable', 'string', 'max:2048'],
            'page_size' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $page = $query->listRentals(
            $request->string('status')->toString() ?: null,
            $request->string('dispute_status')->toString() ?: null,
            $request->string('cursor')->toString() ?: null,
            $request->integer('page_size', 25),
        );

        return $this->success(
            ParticipantRentalResource::collection($page->items)->resolve($request),
            meta: ['page_size' => $page->pageSize, 'has_more' => $page->hasMore, 'next_cursor' => $page->nextCursor],
        );
    }

    public function listStatements(Request $request, PlatformMarketplaceQuery $query)
    {
        $request->validate([
            'cursor' => ['nullable', 'string', 'max:2048'],
            'page_size' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $page = $query->listStatements(
            $request->string('cursor')->toString() ?: null,
            $request->integer('page_size', 25),
        );

        return $this->success(
            ParticipantStatementResource::collection($page->items)->resolve($request),
            meta: ['page_size' => $page->pageSize, 'has_more' => $page->hasMore, 'next_cursor' => $page->nextCursor],
        );
    }

    public function listDisputes(Request $request, PlatformMarketplaceQuery $query)
    {
        $request->validate([
            'status' => ['nullable', 'in:open,under_review,resolved,rejected'],
            'cursor' => ['nullable', 'string', 'max:2048'],
            'page_size' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $page = $query->listDisputes(
            $request->string('status')->toString() ?: null,
            $request->string('cursor')->toString() ?: null,
            $request->integer('page_size', 25),
        );

        $items = collect($page->items)->map(
            fn ($dispute) => (new ParticipantDisputeResource($dispute))->withPlatformNotes(),
        );

        return $this->success(
            $items->map(fn ($resource) => $resource->resolve($request))->all(),
            meta: ['page_size' => $page->pageSize, 'has_more' => $page->hasMore, 'next_cursor' => $page->nextCursor],
        );
    }

    public function showDispute(Request $request, string $dispute_public_id, PlatformMarketplaceQuery $query)
    {
        $dispute = $query->getDispute($dispute_public_id);
        $dispute->load(['statement:id,public_id', 'rental:id,public_id']);

        return $this->success(
            (new ParticipantDisputeResource($dispute))->withPlatformNotes()->resolve($request),
        );
    }

    public function startReview(
        Request $request,
        string $dispute_public_id,
        StartMarketplaceDisputeReviewAction $action,
    ) {
        $dispute = $action->execute(
            (int) $request->user()->id,
            $dispute_public_id,
            (string) $request->header('Idempotency-Key'),
            $this->requests->current()?->correlationId->value ?? 'marketplace-dispute-review',
        );

        $dispute->load(['statement:id,public_id', 'rental:id,public_id']);

        return $this->success(
            (new ParticipantDisputeResource($dispute))->withPlatformNotes()->resolve($request),
        );
    }

    public function addNote(
        PlatformDisputeActionRequest $request,
        string $dispute_public_id,
        AddMarketplaceDisputeNoteAction $action,
    ) {
        $dispute = $action->execute(
            (int) $request->user()->id,
            $dispute_public_id,
            $request->validated('note'),
            $request->validated('visibility'),
            $request->idempotencyKey(),
            $this->requests->current()?->correlationId->value ?? 'marketplace-dispute-note',
        );

        $dispute->load(['statement:id,public_id', 'rental:id,public_id']);

        return $this->success(
            (new ParticipantDisputeResource($dispute))->withPlatformNotes()->resolve($request),
            201,
        );
    }

    public function resolve(
        PlatformDisputeActionRequest $request,
        string $dispute_public_id,
        ResolveMarketplaceDisputeAction $action,
    ) {
        $dispute = $action->execute(
            (int) $request->user()->id,
            $dispute_public_id,
            $request->validated('decision'),
            $request->validated('resolution_code'),
            $request->validated('resolution_summary'),
            $request->idempotencyKey(),
            $this->requests->current()?->correlationId->value ?? 'marketplace-dispute-resolve',
        );

        $dispute->load(['statement:id,public_id', 'rental:id,public_id']);

        return $this->success(
            (new ParticipantDisputeResource($dispute))->withPlatformNotes()->resolve($request),
        );
    }

    public function reviseStatement(
        ReviseStatementRequest $request,
        string $statement_public_id,
        ReviseSettlementStatementAction $action,
    ) {
        $statement = $action->execute(
            (int) $request->user()->id,
            $statement_public_id,
            $request->validated('dispute_public_id'),
            $request->validated('reason_code'),
            $request->validated('lines'),
            $request->idempotencyKey(),
            $this->requests->current()?->correlationId->value ?? 'marketplace-statement-revise',
        );

        return $this->success(
            (new ParticipantStatementResource($statement))->resolve($request),
            201,
        );
    }
}
