<?php

namespace App\Modules\WalletPasses\Domain\ValueObjects;

final readonly class WalletPassUpdateRequest
{
    public function __construct(
        public string $tenantId,
        public string $eventId,
        public string $passSerialNumber,
        public string $provider,
        public string $credentialStatus,
    ) {}
}
