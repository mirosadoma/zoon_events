<?php

namespace App\Modules\WalletPasses\Domain\Results;

final readonly class WalletAdapterResult
{
    public function __construct(
        public string $status,
        public ?string $passUrl = null,
        public ?string $reasonCode = null,
        public ?string $authenticationToken = null,
    ) {}
}
