<?php

namespace App\Modules\AccessControl\Domain\Results;

final readonly class AcsHealthResult
{
    public function __construct(
        public string $status,
        public ?string $reasonCode = null,
    ) {}
}
