# Contract: Model Provider Adapter

One adapter boundary for all model access, mirroring the accepted payment and
notification adapter pattern
([PaymentsServiceProvider.php](../../../app/Modules/Payments/Providers/PaymentsServiceProvider.php)).
No feature code may call a provider directly.

## Interfaces

```php
namespace App\Modules\Ai\Contracts;

interface LlmProvider
{
    public function key(): string;

    public function isAvailable(): bool;

    /** @throws AiProviderException */
    public function complete(AiCompletionRequest $request): AiCompletionResult;
}

interface EmbeddingProvider
{
    public function key(): string;

    public function isAvailable(): bool;

    /**
     * @param  list<string>  $texts
     * @return list<list<float>>  one vector per input, same order
     *
     * @throws AiProviderException
     */
    public function embed(array $texts, string $locale): array;
}
```

`AiCompletionRequest` carries: `systemPrompt`, `userMessage`, ordered
`contextChunks` (each with a citation number, title and text), `locale`,
`maxOutputTokens`, `temperature`, and a `purpose` label (`assistant_answer`,
`insight_summary`, `insight_answer`) used for telemetry and limits. It carries no
tenant secrets and no personal data beyond the visitor's own message.

`AiCompletionResult` carries: `text`, `citedChunkNumbers`, `promptTokens`,
`completionTokens`, `providerKey`, `latencyMs`, `truncated`.

`AiProviderException` maps every failure to a non-sensitive code:
`network_disabled`, `not_configured`, `timeout`, `rate_limited`,
`invalid_request`, `provider_error`, `payload_too_large`.

## Registry and configuration

```php
$this->app->singleton(AiProviderRegistry::class, fn ($app) => new AiProviderRegistry([
    'fake' => $app->make(FakeLlmProvider::class),
    'hosted' => $app->make(HostedLlmProvider::class),
    'self_hosted' => $app->make(SelfHostedLlmProvider::class),
]));
$this->app->bind(LlmProvider::class, fn ($app) => $app->make(AiProviderRegistry::class)->get((string) config('ai.default')));
```

`config/ai.php`:

```php
return [
    'default' => env('AI_DEFAULT_ADAPTER', 'fake'),
    'embedding_default' => env('AI_EMBEDDING_ADAPTER', 'fake'),
    'allow_network' => (bool) env('AI_ALLOW_NETWORK', false),
    'timeout_ms' => (int) env('AI_TIMEOUT_MS', 15000),
    'max_context_chars' => (int) env('AI_MAX_CONTEXT_CHARS', 6000),
    'max_output_tokens' => (int) env('AI_MAX_OUTPUT_TOKENS', 600),
    'max_request_bytes' => (int) env('AI_MAX_REQUEST_BYTES', 65536),
    'assistant' => [
        'visitor_questions_per_minute' => (int) env('AI_VISITOR_QPM', 6),
        'event_questions_per_day' => (int) env('AI_EVENT_QPD', 500),
        'retrieval_candidates' => (int) env('AI_RETRIEVAL_CANDIDATES', 50),
        'retrieval_top_k' => (int) env('AI_RETRIEVAL_TOP_K', 5),
        'transcript_retention_days' => (int) env('AI_TRANSCRIPT_RETENTION_DAYS', 90),
    ],
    'insights' => [
        'cache_minutes' => (int) env('AI_INSIGHT_CACHE_MINUTES', 15),
        'min_bucket_size' => (int) env('AI_MIN_BUCKET_SIZE', 5),
    ],
    'hosted' => [
        'api_url' => env('AI_HOSTED_API_URL'),
        'model' => env('AI_HOSTED_MODEL'),
        'embedding_model' => env('AI_HOSTED_EMBEDDING_MODEL'),
        'secret_reference' => env('AI_HOSTED_SECRET_REFERENCE'),
    ],
    'self_hosted' => [
        'api_url' => env('AI_SELF_HOSTED_API_URL'),
        'model' => env('AI_SELF_HOSTED_MODEL'),
        'embedding_model' => env('AI_SELF_HOSTED_EMBEDDING_MODEL'),
        'secret_reference' => env('AI_SELF_HOSTED_SECRET_REFERENCE'),
    ],
];
```

## Behavioral requirements

- **Disabled by default**: `AI_DEFAULT_ADAPTER=fake` and
  `AI_ALLOW_NETWORK=false`. A network driver whose `allow_network` is false
  reports `isAvailable() === false` and throws `network_disabled` if invoked.
- **Secrets**: credentials are resolved through a secret-reference loader, never
  read inline, never logged, never returned to a client.
- **Timeout and retry**: one configured timeout, at most one retry on
  `timeout`/`provider_error`, no retry on `invalid_request` or `rate_limited`.
- **Size limits**: requests exceeding `max_request_bytes` or
  `max_context_chars` are rejected locally before any network call.
- **Observability**: every attempt emits a telemetry event with purpose,
  provider key, outcome code, latency and token counts, and no prompt or answer
  content.
- **Determinism in tests**: `FakeLlmProvider` returns a deterministic answer
  derived from the provided context chunks and echoes their citation numbers, so
  grounding, citation validation and refusal paths are testable without a
  network. `FakeEmbeddingProvider` returns a stable pseudo-vector per text.
- **Parity**: SaaS selects `hosted`, on-premise selects `self_hosted`; behavior,
  contracts, limits and audit are identical, and only the driver differs.
