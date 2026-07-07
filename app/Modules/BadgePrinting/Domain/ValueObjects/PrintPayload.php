<?php

namespace App\Modules\BadgePrinting\Domain\ValueObjects;

final readonly class PrintPayload
{
    /**
     * @param array<string, string|null> $fields
     */
    public function __construct(
        public array $fields,
        public string $paperSize,
        public string $printerType,
        public string $idempotencyKey,
    ) {}
}
