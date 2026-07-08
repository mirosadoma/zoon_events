<?php

namespace App\Modules\IdentityVerification\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\IdentityVerification\Application\Actions\CaptureConsentAction;
use App\Modules\IdentityVerification\Application\Actions\HandleGovernmentCallbackAction;
use App\Modules\IdentityVerification\Application\Actions\StartGovernmentVerificationAction;
use App\Modules\IdentityVerification\Application\Actions\SubmitFaceCaptureAction;
use App\Modules\IdentityVerification\Application\Support\PublicOrderIdentityContext;
use App\Modules\IdentityVerification\Domain\ValueObjects\IdentityVerificationStatus;
use App\Modules\IdentityVerification\Http\Requests\FaceCaptureSubmitRequest;
use App\Modules\IdentityVerification\Http\Requests\IdentityConsentRequest;
use App\Modules\IdentityVerification\Infrastructure\Persistence\Models\IdentityVerification;
use App\Modules\Shared\Http\Problems\Phase1Problem;
use App\Modules\Shared\Http\Problems\Phase5Problem;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AttendeeIdentityController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly PublicOrderIdentityContext $orderContext,
        private readonly TenantContextStore $tenantContexts,
    ) {}

    public function storeConsent(
        IdentityConsentRequest $request,
        string $eventId,
        string $attendeeId,
        CaptureConsentAction $action,
    ): JsonResponse {
        $this->orderContext->resolve($request, $eventId, $attendeeId);
        $idempotencyKey = (string) $request->header('Idempotency-Key');
        if ($idempotencyKey === '' || strlen($idempotencyKey) > 128) {
            throw Phase1Problem::make('inventory_conflict');
        }

        $validated = $request->validated();
        $result = $action->execute(
            tenantId: (string) $this->attendeeTenantId($eventId, $attendeeId),
            eventId: $eventId,
            attendeeId: $attendeeId,
            noticeVersion: $validated['notice_version'],
            residencyMode: $validated['residency_mode'],
            consented: (bool) $validated['consented'],
        );

        if (! $result['consented']) {
            return $this->success([
                'consented' => false,
                'status' => IdentityVerificationStatus::PENDING,
            ]);
        }

        return $this->success([
            'consented' => true,
            'consent_id' => (string) $result['consent']?->id,
            'status' => $result['status'],
        ], 201);
    }

    public function showVerification(Request $request, string $eventId, string $attendeeId): JsonResponse
    {
        $this->authorizeVerificationRead($request, $eventId, $attendeeId);

        $verification = IdentityVerification::query()
            ->where('event_id', $eventId)
            ->where('attendee_id', $attendeeId)
            ->first();

        if ($verification === null) {
            return $this->success([
                'attendee_id' => $attendeeId,
                'method' => 'gov_identity',
                'status' => IdentityVerificationStatus::PENDING,
                'verified_name' => null,
                'verified_nationality' => null,
                'verified_at' => null,
                'rejection_reason' => null,
            ]);
        }

        return $this->success($this->toStatus($verification));
    }

    public function startVerification(
        Request $request,
        string $eventId,
        string $attendeeId,
        StartGovernmentVerificationAction $action,
    ): JsonResponse {
        $resolved = $this->orderContext->resolve($request, $eventId, $attendeeId);
        $idempotencyKey = (string) $request->header('Idempotency-Key');
        if ($idempotencyKey === '' || strlen($idempotencyKey) > 128) {
            throw Phase1Problem::make('inventory_conflict');
        }

        $outcome = $action->execute(
            tenantId: (string) $resolved['attendee']->tenant_id,
            eventId: $eventId,
            attendeeId: $attendeeId,
            idempotencyKey: $idempotencyKey,
        );

        return $this->success([
            'verification' => $this->toStatus($outcome['verification']),
            'provider_reference' => $outcome['start']->reference,
            'redirect_url' => $outcome['start']->redirectUrl,
        ], 202);
    }

    public function storeFaceCapture(
        FaceCaptureSubmitRequest $request,
        string $eventId,
        string $attendeeId,
        SubmitFaceCaptureAction $action,
    ): JsonResponse {
        $resolved = $this->orderContext->resolve($request, $eventId, $attendeeId);
        $idempotencyKey = (string) $request->header('Idempotency-Key');
        if ($idempotencyKey === '' || strlen($idempotencyKey) > 128) {
            throw Phase1Problem::make('inventory_conflict');
        }

        $outcome = $action->execute(
            tenantId: (string) $resolved['attendee']->tenant_id,
            eventId: $eventId,
            attendeeId: $attendeeId,
            capture: $request->validated('capture'),
            idempotencyKey: $idempotencyKey,
        );

        return $this->success([
            'verification' => $this->toStatus($outcome['verification']),
            'artifact_id' => (string) $outcome['artifact']->id,
            'artifact_type' => (string) $outcome['artifact']->artifact_type,
        ], 202);
    }

    public function governmentCallback(Request $request, HandleGovernmentCallbackAction $action): JsonResponse
    {
        $secret = (string) config('identity-verification.government_callback_secret');
        $signature = (string) $request->header('X-Identity-Callback-Signature');
        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        if ($signature === '' || ! hash_equals($expected, $signature)) {
            throw Phase5Problem::make('identity_callback_invalid');
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();
        $result = $action->execute($payload);

        return $this->success([
            'processed' => $result['processed'],
            'status' => $result['verification']?->status,
        ]);
    }

    private function authorizeVerificationRead(Request $request, string $eventId, string $attendeeId): void
    {
        $token = (string) $request->header('X-Order-Access-Token');
        if ($token !== '') {
            $this->orderContext->resolve($request, $eventId, $attendeeId);

            return;
        }

        $context = $this->tenantContexts->currentOrNull();
        if ($context === null) {
            abort(401);
        }

        Event::query()
            ->where('tenant_id', $context->tenant->id)
            ->findOrFail($eventId);
    }

    private function attendeeTenantId(string $eventId, string $attendeeId): string
    {
        return (string) Attendee::query()
            ->where('event_id', $eventId)
            ->where('id', $attendeeId)
            ->value('tenant_id');
    }

    /** @return array<string, mixed> */
    private function toStatus(IdentityVerification $verification): array
    {
        return [
            'id' => (string) $verification->id,
            'attendee_id' => (string) $verification->attendee_id,
            'method' => (string) $verification->method,
            'status' => (string) $verification->status,
            'verified_name' => $verification->verified_name,
            'verified_nationality' => $verification->verified_nationality,
            'verified_at' => $verification->verified_at?->toIso8601String(),
            'rejection_reason' => $verification->rejection_reason,
        ];
    }
}
