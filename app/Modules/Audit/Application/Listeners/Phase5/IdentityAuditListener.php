<?php

namespace App\Modules\Audit\Application\Listeners\Phase5;

use App\Modules\Audit\Contracts\AuditWriter;
use App\Modules\IdentityVerification\Domain\Events\IdentityArtifactsPurged;
use App\Modules\IdentityVerification\Domain\Events\IdentityConsentCaptured;
use App\Modules\IdentityVerification\Domain\Events\IdentityConsentWithdrawn;
use App\Modules\IdentityVerification\Domain\Events\IdentityFaceCaptureSubmitted;
use App\Modules\IdentityVerification\Domain\Events\IdentityRequirementConfigured;
use App\Modules\IdentityVerification\Domain\Events\IdentityReviewApproved;
use App\Modules\IdentityVerification\Domain\Events\IdentityReviewRejected;
use App\Modules\IdentityVerification\Domain\Events\IdentitySensitiveDataDeleted;
use App\Modules\IdentityVerification\Domain\Events\IdentitySensitiveDataViewed;
use App\Modules\IdentityVerification\Domain\Events\IdentityVerificationResultRecorded;
use App\Modules\IdentityVerification\Domain\Events\IdentityVerificationStarted;

final readonly class IdentityAuditListener
{
    public function __construct(private AuditWriter $audit) {}

    public function handleRequirementConfigured(IdentityRequirementConfigured $event): void
    {
        $this->audit->write(
            'tenant',
            $event->tenantId,
            'identity_requirement.configured',
            'succeeded',
            targetType: 'identity_requirement',
            targetId: $event->requirementId,
            metadata: ['event_id' => $event->eventId, 'ticket_type_id' => $event->ticketTypeId],
        );
    }

    public function handleConsentCaptured(IdentityConsentCaptured $event): void
    {
        $this->audit->write(
            'tenant',
            $event->tenantId,
            'identity_consent.captured',
            'succeeded',
            targetType: 'identity_consent',
            targetId: $event->consentId,
            metadata: ['event_id' => $event->eventId, 'attendee_id' => $event->attendeeId],
        );
    }

    public function handleConsentWithdrawn(IdentityConsentWithdrawn $event): void
    {
        $this->audit->write(
            'tenant',
            $event->tenantId,
            'identity_consent.withdrawn',
            'succeeded',
            targetType: 'identity_consent',
            targetId: $event->consentId,
            metadata: ['event_id' => $event->eventId, 'attendee_id' => $event->attendeeId],
        );
    }

    public function handleVerificationStarted(IdentityVerificationStarted $event): void
    {
        $this->audit->write(
            'tenant',
            $event->tenantId,
            'identity_verification.started',
            'succeeded',
            targetType: 'identity_verification',
            targetId: $event->verificationId,
            metadata: ['event_id' => $event->eventId, 'attendee_id' => $event->attendeeId],
        );
    }

    public function handleVerificationResultRecorded(IdentityVerificationResultRecorded $event): void
    {
        $this->audit->write(
            'tenant',
            $event->tenantId,
            'identity_verification.result_recorded',
            'succeeded',
            targetType: 'identity_verification',
            targetId: $event->verificationId,
            metadata: ['event_id' => $event->eventId, 'attendee_id' => $event->attendeeId, 'status' => $event->status],
        );
    }

    public function handleFaceCaptureSubmitted(IdentityFaceCaptureSubmitted $event): void
    {
        $this->audit->write(
            'tenant',
            $event->tenantId,
            'identity_face_capture.submitted',
            'succeeded',
            targetType: 'identity_verification',
            targetId: $event->verificationId,
            metadata: ['event_id' => $event->eventId, 'attendee_id' => $event->attendeeId, 'artifact_id' => $event->artifactId],
        );
    }

    public function handleReviewApproved(IdentityReviewApproved $event): void
    {
        $this->audit->write(
            'tenant',
            $event->tenantId,
            'identity_review.approved',
            'succeeded',
            reasonCode: null,
            targetType: 'identity_verification',
            targetId: $event->verificationId,
            metadata: ['event_id' => $event->eventId, 'attendee_id' => $event->attendeeId, 'reviewer_id' => $event->reviewerId],
        );
    }

    public function handleReviewRejected(IdentityReviewRejected $event): void
    {
        $this->audit->write(
            'tenant',
            $event->tenantId,
            'identity_review.rejected',
            'denied',
            reasonCode: 'identity_rejected',
            targetType: 'identity_verification',
            targetId: $event->verificationId,
            metadata: ['event_id' => $event->eventId, 'attendee_id' => $event->attendeeId, 'reviewer_id' => $event->reviewerId, 'reason' => $event->reason],
        );
    }

    public function handleSensitiveDataViewed(IdentitySensitiveDataViewed $event): void
    {
        $this->audit->write(
            'tenant',
            $event->tenantId,
            'identity_data.viewed',
            'succeeded',
            targetType: 'identity_verification',
            targetId: $event->verificationId,
            metadata: ['event_id' => $event->eventId, 'attendee_id' => $event->attendeeId, 'actor_id' => $event->actorId],
        );
    }

    public function handleSensitiveDataDeleted(IdentitySensitiveDataDeleted $event): void
    {
        $this->audit->write(
            'tenant',
            $event->tenantId,
            'identity_data.deleted',
            'succeeded',
            targetType: 'identity_verification',
            targetId: $event->verificationId,
            metadata: ['event_id' => $event->eventId, 'attendee_id' => $event->attendeeId, 'actor_id' => $event->actorId],
        );
    }

    public function handleArtifactsPurged(IdentityArtifactsPurged $event): void
    {
        $this->audit->write(
            'tenant',
            $event->tenantId,
            'identity_data.purged',
            'succeeded',
            targetType: 'identity_verification',
            targetId: $event->verificationId,
            metadata: ['event_id' => $event->eventId, 'artifact_count' => $event->artifactCount],
        );
    }
}
