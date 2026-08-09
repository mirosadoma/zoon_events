<?php

namespace App\Modules\Ai\Domain;

final readonly class ChatCompletionResult
{
    /**
     * @param  list<array<string, mixed>>  $structured
     */
    public function __construct(
        public string $answer,
        public string $handler,
        public array $structured = [],
        public ?string $providerKey = null,
        public int $latencyMs = 0,
    ) {}
}
