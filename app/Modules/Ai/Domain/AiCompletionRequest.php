<?php

namespace App\Modules\Ai\Domain;

final readonly class AiCompletionRequest
{
    /**
     * @param  list<ContextChunk>  $contextChunks
     */
    public function __construct(
        public string $systemPrompt,
        public string $userMessage,
        public array $contextChunks,
        public string $locale,
        public int $maxOutputTokens,
        public float $temperature,
        public AiPurpose $purpose,
    ) {}
}
