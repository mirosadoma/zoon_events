<?php

namespace App\Modules\BadgePrinting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Authorization\Policies\Phase3\Phase3Policy;
use App\Modules\BadgePrinting\Application\Actions\CreateBadgePrintJobAction;
use App\Modules\BadgePrinting\Application\Actions\ReprintBadgeAction;
use App\Modules\BadgePrinting\Http\Requests\CreateBadgePrintJobRequest;
use App\Modules\BadgePrinting\Http\Requests\ReprintBadgeRequest;
use App\Modules\BadgePrinting\Http\Resources\BadgePrintJobResource;
use App\Modules\BadgePrinting\Infrastructure\Persistence\Models\BadgePrintJob;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class BadgePrintJobController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly TenantContextStore $contexts,
        private readonly Phase3Policy $policy,
    ) {}

    public function index(Request $request, string $eventId): JsonResponse
    {
        $user = $request->user();

        if ($user === null || (! $this->policy->allows($user, 'printBadge') && ! $this->policy->allows($user, 'reprintBadge'))) {
            abort(403);
        }

        $context = $this->contexts->current();

        $query = BadgePrintJob::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $eventId)
            ->latest('created_at')
            ->limit(200);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        $jobs = $query->get()
            ->map(fn (BadgePrintJob $job): array => (new BadgePrintJobResource($job))->resolve())
            ->values()
            ->all();

        return $this->success($jobs);
    }

    public function store(
        CreateBadgePrintJobRequest $request,
        string $eventId,
        CreateBadgePrintJobAction $action,
    ): JsonResponse {
        $user = $request->user();

        if ($user === null || ! $this->policy->allows($user, 'printBadge')) {
            abort(403);
        }

        $context = $this->contexts->current();

        $job = $action->execute(
            tenantId: $context->tenant->id,
            eventId: $eventId,
            attendeeId: $request->string('attendee_id')->toString(),
            credentialId: $request->string('credential_id')->toString(),
            kioskId: null,
            printedByUserId: (string) $user->id,
        );

        return $this->success((new BadgePrintJobResource($job))->resolve(), 201);
    }

    public function reprint(
        ReprintBadgeRequest $request,
        string $eventId,
        string $badgePrintJobId,
        ReprintBadgeAction $action,
    ): JsonResponse {
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        $context = $this->contexts->current();

        $targetJob = BadgePrintJob::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $eventId)
            ->findOrFail($badgePrintJobId);

        $job = $action->execute(
            actor: $user,
            tenantContext: $context,
            eventId: $eventId,
            attendeeId: $targetJob->attendee_id,
            reason: $request->string('reprint_reason')->toString(),
        );

        return $this->success((new BadgePrintJobResource($job))->resolve(), 201);
    }
}
