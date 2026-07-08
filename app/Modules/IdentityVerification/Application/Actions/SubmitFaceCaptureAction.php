<?php

namespace App\Modules\IdentityVerification\Application\Actions;

use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\IdentityVerification\Application\Support\PublicOrderIdentityContext;
use App\Modules\IdentityVerification\Application\Support\RequirementResolver;
use App\Modules\IdentityVerification\Contracts\FaceCaptureAdapter;
use App\Modules\IdentityVerification\Domain\Events\IdentityFaceCaptureSubmitted;
use App\Modules\IdentityVerification\Domain\ValueObjects\FaceCaptureContext;
use App\Modules\IdentityVerification\Domain\ValueObjects\IdentityReasonCode;
use App\Modules\IdentityVerification\Domain\ValueObjects\IdentityVerificationMethod;
use App\Modules\IdentityVerification\Domain\ValueObjects\IdentityVerificationStatus;
use App\Modules\IdentityVerification\Infrastructure\Persistence\Models\IdentityBiometricArtifact;
use App\Modules\IdentityVerification\Infrastructure\Persistence\Models\IdentityVerification;
use App\Modules\Shared\Application\DataProtection\PersonalDataCipher;
use App\Modules\Shared\Http\Problems\Phase5Problem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class SubmitFaceCaptureAction
{
    public function __construct(
        private FaceCaptureAdapter $adapter,
        private PublicOrderIdentityContext $context,
        private RequirementResolver $requirements,
        private PersonalDataCipher $cipher,
    ) {}

    /** @return array{verification:IdentityVerification,artifact:IdentityBiometricArtifact} */
    public function execute(
        string $tenantId,
        string $eventId,
        string $attendeeId,
        string $capture,
        string $idempotencyKey,
    ): array {
        if ($this->context->activeConsent($tenantId, $eventId, $attendeeId) === null) {
            throw Phase5Problem::make(IdentityReasonCode::CONSENT_MISSING);
        }

        $ticketTypeId = Attendee::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('id', $attendeeId)
            ->value('ticket_type_id');

        if (! $this->requirements->faceFallbackEnabled($tenantId, $eventId, $ticketTypeId !== null ? (string) $ticketTypeId : null)) {
            throw Phase5Problem::make(IdentityReasonCode::PROVIDER_UNAVAILABLE);
        }

        $result = $this->adapter->submitCapture(
            new FaceCaptureContext($tenantId, $eventId, $attendeeId, $idempotencyKey),
            $capture,
        );

        if (in_array($result->status, ['failed', 'unavailable'], true)
            || $result->reasonCode === IdentityReasonCode::PROVIDER_UNAVAILABLE) {
            throw Phase5Problem::make(IdentityReasonCode::PROVIDER_UNAVAILABLE);
        }

        return DB::transaction(function () use ($tenantId, $eventId, $attendeeId, $result): array {
            $verification = IdentityVerification::query()->firstOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'event_id' => $eventId,
                    'attendee_id' => $attendeeId,
                ],
                [
                    'method' => IdentityVerificationMethod::FACE_CAPTURE,
                    'status' => IdentityVerificationStatus::PENDING,
                ],
            );

            $verification->forceFill([
                'method' => IdentityVerificationMethod::FACE_CAPTURE,
                'status' => IdentityVerificationStatus::PENDING,
                'provider' => config('identity-verification.default_face_adapter', 'mock'),
                'provider_reference' => $result->reference,
                'verified_name' => null,
                'verified_nationality' => null,
                'verified_at' => null,
                'rejection_reason' => null,
            ])->save();

            $encrypted = $this->cipher->encrypt(
                (string) $result->reference,
                "{$tenantId}:{$eventId}:identity-biometric",
            );

            $artifact = IdentityBiometricArtifact::query()->create([
                'tenant_id' => $tenantId,
                'verification_id' => $verification->id,
                'artifact_type' => $result->artifactType ?? 'template',
                'storage_reference' => json_encode([
                    'key_id' => $encrypted['key_id'],
                    'ciphertext' => $encrypted['ciphertext'],
                ], JSON_THROW_ON_ERROR),
                'liveness_result' => $result->liveness?->status,
                'retention_until' => CarbonImmutable::now()->addDays(
                    (int) config('identity-verification.retention.biometric_days', 30),
                ),
                'created_at' => CarbonImmutable::now(),
            ]);

            event(new IdentityFaceCaptureSubmitted(
                tenantId: $tenantId,
                eventId: $eventId,
                attendeeId: $attendeeId,
                verificationId: (string) $verification->id,
                artifactId: (string) $artifact->id,
            ));

            return [
                'verification' => $verification->refresh(),
                'artifact' => $artifact,
            ];
        });
    }
}
