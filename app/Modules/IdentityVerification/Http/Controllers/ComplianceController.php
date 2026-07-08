<?php

namespace App\Modules\IdentityVerification\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\IdentityVerification\Application\Actions\DeleteIdentityDataAction;
use App\Modules\IdentityVerification\Application\Actions\ViewIdentityDataAction;
use App\Modules\IdentityVerification\Http\Requests\IdentityDataDeleteRequest;
use App\Modules\IdentityVerification\Infrastructure\Persistence\Models\IdentityVerification;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\Tenancy\Domain\Context\TenantContext;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use Illuminate\Http\JsonResponse;

final class ComplianceController extends Controller
{
    use RespondsWithApi;

    public function __construct(private readonly TenantContextStore $contexts) {}

    public function show(string $eventId, string $attendeeId, ViewIdentityDataAction $action): JsonResponse
    {
        $context = $this->contexts->current();
        $this->assertScopedAttendee($context, $eventId, $attendeeId);

        return $this->success($action->execute($context, $eventId, $attendeeId));
    }

    public function destroy(
        IdentityDataDeleteRequest $request,
        string $eventId,
        string $attendeeId,
        DeleteIdentityDataAction $action,
    ): JsonResponse {
        $context = $this->contexts->current();
        $this->assertScopedAttendee($context, $eventId, $attendeeId);

        $verification = $action->execute(
            $context,
            $eventId,
            $attendeeId,
            (string) $request->validated('reason'),
        );

        return $this->success([
            'id' => (string) $verification->id,
            'attendee_id' => (string) $verification->attendee_id,
            'status' => (string) $verification->status,
        ]);
    }

    private function assertScopedAttendee(TenantContext $context, string $eventId, string $attendeeId): void
    {
        Event::query()
            ->where('tenant_id', $context->tenant->id)
            ->findOrFail($eventId);

        IdentityVerification::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $eventId)
            ->where('attendee_id', $attendeeId)
            ->firstOrFail();
    }
}
