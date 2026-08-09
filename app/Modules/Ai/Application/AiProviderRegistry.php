<?php

namespace App\Modules\Ai\Application;

use App\Modules\Ai\Contracts\EmbeddingProvider;
use App\Modules\Ai\Contracts\LlmProvider;
use InvalidArgumentException;

final readonly class AiProviderRegistry
{
    /**
     * @param  array<string, LlmProvider>  $llmProviders
     * @param  array<string, EmbeddingProvider>  $embeddingProviders
     */
    public function __construct(
        private array $llmProviders,
        private array $embeddingProviders,
    ) {}

    public function getLlm(string $key): LlmProvider
    {
        return $this->llmProviders[$key] ?? throw new InvalidArgumentException("LLM provider [{$key}] is not configured.");
    }

    public function getEmbedding(string $key): EmbeddingProvider
    {
        return $this->embeddingProviders[$key] ?? throw new InvalidArgumentException("Embedding provider [{$key}] is not configured.");
    }

    /**
     * @return array<string, LlmProvider>
     */
    public function allLlm(): array
    {
        return $this->llmProviders;
    }

    /**
     * @return array<string, EmbeddingProvider>
     */
    public function allEmbedding(): array
    {
        return $this->embeddingProviders;
    }
}
