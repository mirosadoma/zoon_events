<?php

namespace App\Modules\WalletPasses\Domain\Events;

final readonly class WalletPassGenerationDenied
{
    public function __construct(
        public string $tenantId,
        public string $eventId,
        public string $credentialId,
        public string $provider,
    ) {}
}
