<?php

namespace App\Modules\Orders\Domain\Events;

final readonly class FreeRegistrationCompleted
{
    public function __construct(
        public string $tenantId,
        public string $eventId,
        public string $orderId,
        public string $attendeeId,
        public string $credentialId,
    ) {}
}
