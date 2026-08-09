<?php

namespace App\Modules\Ai\Infrastructure\Adapters;

use App\Modules\Ai\Contracts\AiSecretLoader;
use App\Modules\Ai\Domain\AiCompletionRequest;
use App\Modules\Ai\Domain\AiCompletionResult;
use App\Modules\Ai\Domain\AiProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

abstract class OpenAiCompatibleClient
{
    protected int $maxRetries = 1;

    public function __construct(
        protected readonly AiSecretLoader $secretLoader,
    ) {}

    abstract protected function providerKey(): string;

    abstract protected function apiUrl(): ?string;

    abstract protected function model(): ?string;

    abstract protected function embeddingModel(): ?string;

    abstract protected function secretReference(): ?string;

    protected function isConfigured(): bool
    {
        return $this->apiUrl() !== null
            && $this->apiUrl() !== ''
            && $this->secretReference() !== null
            && $this->secretReference() !== '';
    }

    protected function networkAllowed(): bool
    {
        return (bool) config('ai.allow_network', false);
    }

    protected function timeoutMs(): int
    {
        return (int) config('ai.timeout_ms', 15000);
    }

    protected function maxRequestBytes(): int
    {
        return (int) config('ai.max_request_bytes', 65536);
    }

    /**
     * @param  list<array<string, mixed>>  $messages
     * @param  list<array<string, mixed>>  $tools
     * @return array<string, mixed>
     */
    public function chatCompletions(array $messages, array $tools = []): array
    {
        if (! $this->networkAllowed()) {
            throw AiProviderException::networkDisabled();
        }

        if (! $this->isConfigured()) {
            throw AiProviderException::notConfigured();
        }

        $payload = [
            'model' => $this->model(),
            'messages' => $messages,
            'max_tokens' => (int) config('ai.max_output_tokens', 600),
            'temperature' => 0.3,
        ];

        if ($tools !== []) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = 'auto';
        }

        $jsonPayload = json_encode($payload);
        if (strlen($jsonPayload) > $this->maxRequestBytes()) {
            throw AiProviderException::payloadTooLarge();
        }

        return $this->makeRequest('/chat/completions', $payload);
    }

    protected function doComplete(AiCompletionRequest $request): AiCompletionResult
    {
        if (! $this->networkAllowed()) {
            throw AiProviderException::networkDisabled();
        }

        if (! $this->isConfigured()) {
            throw AiProviderException::notConfigured();
        }

        $messages = [
            ['role' => 'system', 'content' => $request->systemPrompt],
            ['role' => 'user', 'content' => $request->userMessage],
        ];

        $payload = [
            'model' => $this->model(),
            'messages' => $messages,
            'max_tokens' => $request->maxOutputTokens,
            'temperature' => $request->temperature,
        ];

        $jsonPayload = json_encode($payload);
        if (strlen($jsonPayload) > $this->maxRequestBytes()) {
            throw AiProviderException::payloadTooLarge();
        }

        $startTime = hrtime(true);
        $response = $this->makeRequest('/chat/completions', $payload);
        $latencyMs = (int) ((hrtime(true) - $startTime) / 1_000_000);

        $text = $response['choices'][0]['message']['content'] ?? '';
        $usage = $response['usage'] ?? [];

        $citedNumbers = $this->extractCitations($text);

        return new AiCompletionResult(
            text: $text,
            citedChunkNumbers: $citedNumbers,
            promptTokens: $usage['prompt_tokens'] ?? 0,
            completionTokens: $usage['completion_tokens'] ?? 0,
            providerKey: $this->providerKey(),
            latencyMs: $latencyMs,
            truncated: ($response['choices'][0]['finish_reason'] ?? '') === 'length',
        );
    }

    /**
     * @param  list<string>  $texts
     * @return list<list<float>>
     */
    protected function doEmbed(array $texts, string $locale): array
    {
        if (! $this->networkAllowed()) {
            throw AiProviderException::networkDisabled();
        }

        if (! $this->isConfigured() || $this->embeddingModel() === null) {
            throw AiProviderException::notConfigured();
        }

        $payload = [
            'model' => $this->embeddingModel(),
            'input' => $texts,
        ];

        $jsonPayload = json_encode($payload);
        if (strlen($jsonPayload) > $this->maxRequestBytes()) {
            throw AiProviderException::payloadTooLarge();
        }

        $response = $this->makeRequest('/embeddings', $payload);

        $embeddings = [];
        foreach ($response['data'] ?? [] as $item) {
            $embeddings[] = $item['embedding'] ?? [];
        }

        return $embeddings;
    }

    /**
     * @return array<string, mixed>
     */
    protected function makeRequest(string $endpoint, array $payload): array
    {
        $url = rtrim($this->apiUrl(), '/').'/'.ltrim($endpoint, '/');
        $apiKey = $this->secretLoader->load($this->secretReference());
        $timeoutSeconds = (int) ceil($this->timeoutMs() / 1000);

        $attempt = 0;
        $lastException = null;

        while ($attempt <= $this->maxRetries) {
            try {
                $response = Http::timeout($timeoutSeconds)
                    ->withHeaders([
                        'Authorization' => "Bearer {$apiKey}",
                        'Content-Type' => 'application/json',
                    ])
                    ->post($url, $payload);

                if ($response->successful()) {
                    return $response->json();
                }

                $status = $response->status();

                if ($status === 429) {
                    throw AiProviderException::rateLimited();
                }

                if ($status >= 400 && $status < 500) {
                    throw AiProviderException::invalidRequest($response->body());
                }

                $lastException = AiProviderException::providerError("HTTP {$status}");

            } catch (ConnectionException $e) {
                $lastException = AiProviderException::timeout();
            } catch (RequestException $e) {
                $lastException = AiProviderException::providerError($e->getMessage());
            }

            $attempt++;
        }

        throw $lastException ?? AiProviderException::providerError();
    }

    /**
     * @return list<int>
     */
    protected function extractCitations(string $text): array
    {
        preg_match_all('/\[(\d+)\]/', $text, $matches);

        return array_values(array_unique(array_map('intval', $matches[1] ?? [])));
    }
}
