<?php

namespace App\Modules\Ai\Infrastructure\Adapters\SelfHosted;

use App\Modules\Ai\Contracts\LlmProvider;
use App\Modules\Ai\Domain\AiCompletionRequest;
use App\Modules\Ai\Domain\AiCompletionResult;
use App\Modules\Ai\Infrastructure\Adapters\OpenAiCompatibleClient;

final class SelfHostedLlmProvider extends OpenAiCompatibleClient implements LlmProvider
{
    public function key(): string
    {
        return 'self_hosted';
    }

    public function isAvailable(): bool
    {
        return $this->networkAllowed() && $this->isConfigured();
    }

    public function complete(AiCompletionRequest $request): AiCompletionResult
    {
        return $this->doComplete($request);
    }

    protected function providerKey(): string
    {
        return $this->key();
    }

    protected function apiUrl(): ?string
    {
        return config('ai.self_hosted.api_url');
    }

    protected function model(): ?string
    {
        return config('ai.self_hosted.model');
    }

    protected function embeddingModel(): ?string
    {
        return config('ai.self_hosted.embedding_model');
    }

    protected function secretReference(): ?string
    {
        return config('ai.self_hosted.secret_reference');
    }
}
