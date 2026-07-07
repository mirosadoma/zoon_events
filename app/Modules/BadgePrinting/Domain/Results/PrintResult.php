<?php

namespace App\Modules\BadgePrinting\Domain\Results;

final readonly class PrintResult
{
    public function __construct(
        public string $status,
        public ?string $reasonCode,
        public ?string $confirmationReference,
    ) {}
}
