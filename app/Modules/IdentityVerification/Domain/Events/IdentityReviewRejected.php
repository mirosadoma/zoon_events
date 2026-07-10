<?php

namespace App\Modules\IdentityVerification\Domain\Events;

final readonly class IdentityReviewRejected
{
    public function __construct(
        public string $tenantId,
        public string $eventId,
        public string $attendeeId,
        public string $verificationId,
        public string $reviewerId,
        public string $reason,
    ) {}
}
