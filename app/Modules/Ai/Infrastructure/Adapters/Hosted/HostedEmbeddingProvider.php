<?php

namespace App\Modules\Ai\Infrastructure\Adapters\Hosted;

use App\Modules\Ai\Contracts\EmbeddingProvider;
use App\Modules\Ai\Infrastructure\Adapters\OpenAiCompatibleClient;

final class HostedEmbeddingProvider extends OpenAiCompatibleClient implements EmbeddingProvider
{
    public function key(): string
    {
        return 'hosted';
    }

    public function isAvailable(): bool
    {
        return $this->networkAllowed() && $this->isConfigured() && $this->embeddingModel() !== null;
    }

    public function embed(array $texts, string $locale): array
    {
        return $this->doEmbed($texts, $locale);
    }

    protected function providerKey(): string
    {
        return $this->key();
    }

    protected function apiUrl(): ?string
    {
        return config('ai.hosted.api_url');
    }

    protected function model(): ?string
    {
        return config('ai.hosted.model');
    }

    protected function embeddingModel(): ?string
    {
        return config('ai.hosted.embedding_model');
    }

    protected function secretReference(): ?string
    {
        return config('ai.hosted.secret_reference');
    }
}
