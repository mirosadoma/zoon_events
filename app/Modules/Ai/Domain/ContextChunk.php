<?php

namespace App\Modules\Ai\Domain;

final readonly class ContextChunk
{
    public function __construct(
        public int $number,
        public string $title,
        public string $text,
        public string $sourceType,
        public string $sourceId,
    ) {}
}
