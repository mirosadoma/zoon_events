<?php

namespace App\Modules\Ai\Contracts;

use App\Modules\Ai\Domain\AiCompletionRequest;
use App\Modules\Ai\Domain\AiCompletionResult;
use App\Modules\Ai\Domain\AiProviderException;

interface LlmProvider
{
    public function key(): string;

    public function isAvailable(): bool;

    /** @throws AiProviderException */
    public function complete(AiCompletionRequest $request): AiCompletionResult;
}
