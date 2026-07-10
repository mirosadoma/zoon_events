<?php

namespace App\Modules\Integrations\Contracts;

final readonly class AdapterDescriptor
{
    public function __construct(
        public string $key,
        public string $capability,
        public string $version,
        public bool $testingOnly = false,
    ) {}
}
