<?php

namespace App\Modules\Credentials\Domain\Events;

final readonly class CredentialLifecycleChanged
{
    public function __construct(
        public string $tenantId,
        public string $eventId,
        public string $credentialId,
        public string $transition,
        public ?string $replacementId = null,
    ) {}
}
