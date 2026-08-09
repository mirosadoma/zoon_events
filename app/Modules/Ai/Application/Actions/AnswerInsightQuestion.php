<?php

namespace App\Modules\Ai\Application\Actions;

use App\Modules\Ai\Application\Support\AiLimiter;
use App\Modules\Ai\Application\Support\EventMetricsPayload;
use App\Modules\Ai\Application\Support\OrganizerFaqMatcher;
use App\Modules\Ai\Application\Support\PromptBuilder;
use App\Modules\Ai\Contracts\KnowledgeRetriever;
use App\Modules\Ai\Contracts\LlmProvider;
use App\Modules\Ai\Domain\AiProviderException;
use App\Modules\Ai\Domain\ContextChunk;
use App\Modules\Ai\Infrastructure\Persistence\Models\EventAssistantSettings;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;

final class AnswerInsightQuestion
{
    public function __construct(
        private readonly LlmProvider $llmProvider,
        private readonly PromptBuilder $promptBuilder,
        private readonly OrganizerFaqMatcher $faqMatcher,
        private readonly KnowledgeRetriever $retriever,
        private readonly AiLimiter $limiter,
    ) {}

    /**
     * @param  array<string, mixed>  $metrics
     * @return array{outcome: string, answer: ?string, metrics_used: array<string, mixed>}
     */
    public function execute(
        int $tenantId,
        int $eventId,
        array $metrics,
        string $question,
        string $locale,
    ): array {
        $payload = new EventMetricsPayload($metrics);
        $builtPayload = $payload->build();

        if ($this->isOutOfScope($question)) {
            return [
                'outcome' => 'out_of_scope',
                'answer' => null,
                'metrics_used' => $builtPayload,
            ];
        }

        $faq = $this->faqMatcher->match($tenantId, $eventId, $question, $locale);
        if ($faq !== null) {
            return [
                'outcome' => 'answered',
                'answer' => $faq->answerFor($locale),
                'metrics_used' => $builtPayload,
            ];
        }

        if (! $this->llmProvider->isAvailable()) {
            throw AiProviderException::notConfigured();
        }

        $settings = EventAssistantSettings::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->first();

        $chunks = [];
        if ($settings !== null && $settings->index_status === 'ready' && $settings->index_version > 0) {
            $chunks = $this->retriever->retrieve(
                $tenantId,
                $eventId,
                $settings->index_version,
                $question,
                $locale,
                (int) config('ai.assistant.retrieval_top_k', 5),
                $this->limiter->maxContextChars(),
            );
        }

        if ($payload->isEmpty() && $chunks === []) {
            return [
                'outcome' => 'insufficient_data',
                'answer' => null,
                'metrics_used' => $builtPayload,
            ];
        }

        $event = Event::query()->where('tenant_id', $tenantId)->find($eventId);
        $eventName = $locale === 'ar' ? ($event?->name_ar ?? 'Event') : ($event?->name_en ?? 'Event');

        $contextChunks = [];
        $number = 1;
        foreach ($chunks as $chunk) {
            $contextChunks[] = new ContextChunk(
                number: $number++,
                title: $chunk->title ?? 'Source',
                text: $chunk->content,
                sourceType: $chunk->sourceType,
                sourceId: $chunk->sourceId,
            );
        }

        $request = $this->promptBuilder->buildInsightQuestionRequest(
            $question,
            $builtPayload,
            $locale,
            $eventName,
            $contextChunks,
        );

        $result = $this->llmProvider->complete($request);

        return [
            'outcome' => 'answered',
            'answer' => $result->text,
            'metrics_used' => $builtPayload,
        ];
    }

    private function isOutOfScope(string $question): bool
    {
        $outOfScopePatterns = [
            '/attendee.*(name|email|phone|address)/i',
            '/who\s+(registered|attended|checked)/i',
            '/list\s+(of\s+)?(attendees|registrants)/i',
            '/personal\s+(data|information)/i',
            '/specific\s+(person|attendee)/i',
        ];

        foreach ($outOfScopePatterns as $pattern) {
            if (preg_match($pattern, $question)) {
                return true;
            }
        }

        return false;
    }
}
