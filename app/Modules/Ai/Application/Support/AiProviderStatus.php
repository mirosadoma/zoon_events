<?php

namespace App\Modules\Ai\Application\Support;

use App\Modules\Ai\Contracts\AiSecretLoader;
use App\Modules\Ai\Contracts\LlmProvider;
use App\Modules\Ai\Testing\FakeLlmProvider;

final class AiProviderStatus
{
    public function __construct(
        private readonly LlmProvider $llmProvider,
        private readonly AiSecretLoader $secretLoader,
    ) {}

    /**
     * @return array{available: bool, adapter: string, reason: ?string, hint: ?string}
     */
    public function describe(): array
    {
        $adapter = (string) config('ai.default', 'fake');

        if ($this->llmProvider instanceof FakeLlmProvider) {
            return [
                'available' => $this->llmProvider->isAvailable(),
                'adapter' => $adapter,
                'reason' => $this->llmProvider->isAvailable() ? null : 'fake_disabled',
                'hint' => $this->llmProvider->isAvailable() ? null : 'The fake provider was marked unavailable.',
            ];
        }

        if (! (bool) config('ai.allow_network', false)) {
            return [
                'available' => false,
                'adapter' => $adapter,
                'reason' => 'network_disabled',
                'hint' => 'Set AI_ALLOW_NETWORK=true in .env, or use AI_DEFAULT_ADAPTER=fake for local development.',
            ];
        }

        $configKey = $adapter === 'self_hosted' ? 'ai.self_hosted' : 'ai.hosted';
        $apiUrl = config("{$configKey}.api_url");
        $model = config("{$configKey}.model");
        $secretReference = config("{$configKey}.secret_reference");

        if ($apiUrl === null || $apiUrl === '' || $model === null || $model === '') {
            $other = $adapter === 'self_hosted' ? 'hosted' : 'self_hosted';
            $otherUrl = config("ai.{$other}.api_url");

            if ($otherUrl !== null && $otherUrl !== '') {
                return [
                    'available' => false,
                    'adapter' => $adapter,
                    'reason' => 'adapter_mismatch',
                    'hint' => "AI_DEFAULT_ADAPTER={$adapter} but OpenAI settings are under AI_".strtoupper($other)."_* — switch AI_DEFAULT_ADAPTER={$other} or copy the values to AI_".strtoupper($adapter).'_*.',
                ];
            }

            return [
                'available' => false,
                'adapter' => $adapter,
                'reason' => 'not_configured',
                'hint' => 'Set AI_'.strtoupper($adapter).'_API_URL and AI_'.strtoupper($adapter).'_MODEL in .env.',
            ];
        }

        if ($secretReference === null || $secretReference === '') {
            return [
                'available' => false,
                'adapter' => $adapter,
                'reason' => 'missing_secret_reference',
                'hint' => 'Set AI_'.strtoupper($adapter).'_SECRET_REFERENCE=OPENAI_API_KEY and add OPENAI_API_KEY=sk-... to .env.',
            ];
        }

        try {
            $this->secretLoader->load($secretReference);
        } catch (\Throwable) {
            return [
                'available' => false,
                'adapter' => $adapter,
                'reason' => 'missing_api_key',
                'hint' => "Add {$secretReference}=sk-... to your .env file.",
            ];
        }

        return [
            'available' => true,
            'adapter' => $this->llmProvider->key(),
            'reason' => null,
            'hint' => null,
        ];
    }
}
