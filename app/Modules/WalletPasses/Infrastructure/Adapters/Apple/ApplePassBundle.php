<?php

namespace App\Modules\WalletPasses\Infrastructure\Adapters\Apple;

final readonly class ApplePassBundle
{
    public function __construct(
        public string $path,
        public string $authenticationToken,
        public string $passUrl,
    ) {}
}
