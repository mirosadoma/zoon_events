<?php

namespace App\Modules\Registration\Domain\Events;

final readonly class RegistrationFormPublished
{
    public function __construct(
        public string $tenantId,
        public string $eventId,
        public string $formVersionId,
        public string $actorId,
    ) {}
}
