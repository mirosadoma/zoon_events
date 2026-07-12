<?php

namespace App\Modules\IdentityVerification\Application\Actions;

use App\Modules\IdentityVerification\Domain\Events\IdentityReviewApproved;
use App\Modules\IdentityVerification\Domain\Events\IdentityReviewRejected;
use App\Modules\IdentityVerification\Domain\ValueObjects\IdentityVerificationMethod;
use App\Modules\IdentityVerification\Domain\ValueObjects\IdentityVerificationStatus;
use App\Modules\IdentityVerification\Infrastructure\Persistence\Models\IdentityVerification;
use App\Modules\Tenancy\Domain\Context\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ReviewVerificationAction
{
    public function execute(
        TenantContext $context,
        string $eventId,
        string $verificationId,
        string $decision,
        ?string $reason,
    ): IdentityVerification {
        if ($decision === 'reject' && ($reason === null || trim($reason) === '')) {
            throw ValidationException::withMessages([
                'reason' => ['A reason is required when rejecting a verification.'],
            ]);
        }

        return DB::transaction(function () use ($context, $eventId, $verificationId, $decision, $reason): IdentityVerification {
            $verification = IdentityVerification::query()
                ->where('tenant_id', $context->tenant->id)
                ->where('event_id', $eventId)
                ->where('id', $verificationId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($verification->status !== IdentityVerificationStatus::PENDING) {
                return $verification;
            }

            if ($decision === 'approve') {
                $status = $verification->method === IdentityVerificationMethod::MANUAL_REVIEW
                    ? IdentityVerificationStatus::MANUALLY_APPROVED
                    : IdentityVerificationStatus::FACE_VERIFIED;

                $verification->forceFill([
                    'status' => $status,
                    'verified_at' => CarbonImmutable::now(),
                    'manual_review_by' => $context->actor->id,
                    'manual_review_at' => CarbonImmutable::now(),
                    'rejection_reason' => null,
                    'retention_until' => CarbonImmutable::now()->addDays(
                        (int) config('identity-verification.retention.verification_days', 365),
                    ),
                ])->save();

                $approved = $verification->refresh();
                DB::afterCommit(function () use ($context, $eventId, $approved): void {
                    event(new IdentityReviewApproved(
                        tenantId: (string) $context->tenant->id,
                        eventId: $eventId,
                        attendeeId: (string) $approved->attendee_id,
                        verificationId: (string) $approved->id,
                        reviewerId: (string) $context->actor->id,
                    ));
                });

                return $approved;
            }

            $verification->forceFill([
                'status' => IdentityVerificationStatus::REJECTED,
                'rejection_reason' => trim((string) $reason),
                'manual_review_by' => $context->actor->id,
                'manual_review_at' => CarbonImmutable::now(),
                'verified_at' => null,
            ])->save();

            $rejected = $verification->refresh();
            DB::afterCommit(function () use ($context, $eventId, $rejected, $reason): void {
                event(new IdentityReviewRejected(
                    tenantId: (string) $context->tenant->id,
                    eventId: $eventId,
                    attendeeId: (string) $rejected->attendee_id,
                    verificationId: (string) $rejected->id,
                    reviewerId: (string) $context->actor->id,
                    reason: trim((string) $reason),
                ));
            });

            return $rejected;
        });
    }
}
