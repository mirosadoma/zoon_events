<?php

namespace App\Modules\Ai\Application\Actions;

use App\Models\User;
use App\Modules\Ai\Application\Support\AiLimiter;
use App\Modules\Ai\Application\Support\EventMetricsPayload;
use App\Modules\Ai\Application\Support\PromptBuilder;
use App\Modules\Ai\Contracts\LlmProvider;
use App\Modules\Ai\Domain\AiProviderException;
use App\Modules\Ai\Infrastructure\Persistence\Models\EventInsightSummary;
use App\Modules\Audit\Contracts\AuditWriter;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;

final class GenerateEventInsight
{
    public function __construct(
        private readonly LlmProvider $llmProvider,
        private readonly PromptBuilder $promptBuilder,
        private readonly AiLimiter $limiter,
        private readonly AuditWriter $auditWriter,
    ) {}

    /**
     * @param  array<string, mixed>  $metrics
     * @return array{summary: ?string, highlights: ?array<array{kind: string, text: string}>, cached: bool, generated_at: string, expires_at: string, metrics_used: array<string, mixed>}
     */
    public function execute(
        int $tenantId,
        int $eventId,
        array $metrics,
        string $metricWindow,
        string $locale,
        User $actor,
        bool $refresh = false,
    ): array {
        $payload = new EventMetricsPayload($metrics, $this->limiter->minBucketSize());
        $builtPayload = $payload->build();
        $payloadHash = $payload->hash();

        if ($payload->isEmpty()) {
            return [
                'summary' => null,
                'highlights' => null,
                'cached' => false,
                'generated_at' => now()->toIso8601String(),
                'expires_at' => now()->toIso8601String(),
                'metrics_used' => $builtPayload,
                'insufficient_data' => true,
            ];
        }

        if (! $refresh) {
            $cached = EventInsightSummary::query()
                ->where('tenant_id', $tenantId)
                ->where('event_id', $eventId)
                ->where('metric_window', $metricWindow)
                ->where('payload_hash', $payloadHash)
                ->where('expires_at', '>', now())
                ->first();

            if ($cached !== null) {
                return [
                    'summary' => $locale === 'ar' ? $cached->summary_ar : $cached->summary_en,
                    'highlights' => $cached->highlights,
                    'cached' => true,
                    'generated_at' => $cached->generated_at->toIso8601String(),
                    'expires_at' => $cached->expires_at->toIso8601String(),
                    'metrics_used' => $cached->metrics_payload,
                ];
            }
        }

        if (! $this->llmProvider->isAvailable()) {
            throw AiProviderException::notConfigured();
        }

        $event = Event::query()->where('tenant_id', $tenantId)->find($eventId);
        $eventName = $locale === 'ar' ? ($event?->name_ar ?? 'Event') : ($event?->name_en ?? 'Event');

        $request = $this->promptBuilder->buildInsightSummaryRequest($builtPayload, $locale, $eventName);

        $result = $this->llmProvider->complete($request);

        $parsedHighlights = $this->parseHighlights($result->text);
        $summary = $this->extractSummary($result->text);

        $expiresAt = now()->addMinutes($this->limiter->insightCacheMinutes());

        $insight = EventInsightSummary::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'event_id' => $eventId,
                'metric_window' => $metricWindow,
                'payload_hash' => $payloadHash,
            ],
            [
                'metrics_payload' => $builtPayload,
                'summary_en' => $locale === 'en' ? $summary : null,
                'summary_ar' => $locale === 'ar' ? $summary : null,
                'highlights' => $parsedHighlights,
                'provider_key' => $result->providerKey,
                'generated_by_user_id' => $actor->id,
                'generated_at' => now(),
                'expires_at' => $expiresAt,
            ],
        );

        $this->auditWriter->write(
            scope: 'tenant',
            tenantId: (string) $tenantId,
            action: 'ai_insight.generated',
            outcome: 'succeeded',
            actor: $actor,
            targetType: 'event',
            targetId: (string) $eventId,
            metadata: ['metric_window' => $metricWindow, 'provider_key' => $result->providerKey],
        );

        return [
            'summary' => $summary,
            'highlights' => $parsedHighlights,
            'cached' => false,
            'generated_at' => $insight->generated_at->toIso8601String(),
            'expires_at' => $insight->expires_at->toIso8601String(),
            'metrics_used' => $builtPayload,
        ];
    }

    private function extractSummary(string $text): string
    {
        $lines = explode("\n", $text);
        $summaryLines = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (str_starts_with($line, '-') || str_starts_with($line, '*') || str_starts_with($line, '•')) {
                break;
            }
            $summaryLines[] = $line;
        }

        return implode(' ', $summaryLines);
    }

    /**
     * @return list<array{kind: string, text: string}>
     */
    private function parseHighlights(string $text): array
    {
        $highlights = [];
        $lines = explode("\n", $text);

        foreach ($lines as $line) {
            $line = trim($line);
            if (! preg_match('/^[-*•]\s*(.+)$/', $line, $matches)) {
                continue;
            }

            $bulletText = trim($matches[1]);

            $kind = 'trend';
            if (preg_match('/risk|warning|concern|drop|decline|peak|capacity/i', $bulletText)) {
                $kind = 'risk';
            } elseif (preg_match('/action|recommend|suggest|consider|should/i', $bulletText)) {
                $kind = 'action';
            }

            $highlights[] = ['kind' => $kind, 'text' => $bulletText];
        }

        return array_slice($highlights, 0, 5);
    }
}
