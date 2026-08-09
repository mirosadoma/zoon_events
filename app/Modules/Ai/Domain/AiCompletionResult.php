<?php

namespace App\Modules\Ai\Domain;

final readonly class AiCompletionResult
{
    /**
     * @param  list<int>  $citedChunkNumbers
     */
    public function __construct(
        public string $text,
        public array $citedChunkNumbers,
        public int $promptTokens,
        public int $completionTokens,
        public string $providerKey,
        public int $latencyMs,
        public bool $truncated = false,
    ) {}
}
