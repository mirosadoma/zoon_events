<?php

namespace App\Modules\WalletPasses\Domain\Events;

final readonly class WalletPassGenerated
{
    public function __construct(
        public string $tenantId,
        public string $eventId,
        public string $walletPassId,
        public string $provider,
    ) {}
}
