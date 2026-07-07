<?php

namespace App\Modules\Attendees\Domain;

final readonly class WalkUpRegistrationResult
{
    public function __construct(
        public string $attendeeId,
        public ?string $credentialId,
        public string $origin,
        public string $paymentStatus,
    ) {}
}
