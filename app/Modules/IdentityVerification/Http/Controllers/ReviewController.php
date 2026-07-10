<?php

namespace App\Modules\IdentityVerification\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\IdentityVerification\Application\Actions\ReviewVerificationAction;
use App\Modules\IdentityVerification\Application\Queries\PendingReviewQueue;
use App\Modules\IdentityVerification\Http\Requests\IdentityReviewDecisionRequest;
use App\Modules\IdentityVerification\Infrastructure\Persistence\Models\IdentityVerification;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\Tenancy\Domain\Context\TenantContext;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use Illuminate\Http\JsonResponse;

final class ReviewController extends Controller
{
    use RespondsWithApi;

    public function __construct(private readonly TenantContextStore $contexts) {}

    public function index(string $eventId, PendingReviewQueue $queue): JsonResponse
    {
        $context = $this->contexts->current();
        $this->assertScopedEvent($context, $eventId);

        $items = $queue->forEvent((string) $context->tenant->id, $eventId)
            ->map(fn (IdentityVerification $row): array => $this->toQueueRow($row))
            ->values()
            ->all();

        return $this->success($items);
    }

    public function store(
        IdentityReviewDecisionRequest $request,
        string $eventId,
        string $verificationId,
        ReviewVerificationAction $action,
    ): JsonResponse {
        $context = $this->contexts->current();
        $this->assertScopedEvent($context, $eventId);

        $validated = $request->validated();
        $saved = $action->execute(
            $context,
            $eventId,
            $verificationId,
            $validated['decision'],
            $validated['reason'] ?? null,
        );

        return $this->success($this->toQueueRow($saved));
    }

    private function assertScopedEvent(TenantContext $context, string $eventId): void
    {
        Event::query()
            ->where('tenant_id', $context->tenant->id)
            ->findOrFail($eventId);
    }

    /** @return array<string, mixed> */
    private function toQueueRow(IdentityVerification $verification): array
    {
        return [
            'id' => (string) $verification->id,
            'attendee_id' => (string) $verification->attendee_id,
            'method' => (string) $verification->method,
            'status' => (string) $verification->status,
            'provider_reference' => $verification->provider_reference,
            'submitted_at' => $verification->updated_at?->toIso8601String(),
            'rejection_reason' => $verification->rejection_reason,
        ];
    }
}
