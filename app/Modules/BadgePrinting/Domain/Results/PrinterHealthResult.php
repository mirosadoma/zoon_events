<?php

namespace App\Modules\BadgePrinting\Domain\Results;

final readonly class PrinterHealthResult
{
    public function __construct(
        public string $status,
        public ?string $reasonCode,
    ) {}
}
