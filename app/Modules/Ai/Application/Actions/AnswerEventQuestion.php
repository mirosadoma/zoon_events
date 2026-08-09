<?php

namespace App\Modules\Ai\Application\Actions;

use App\Modules\Ai\Application\Support\AiLimiter;
use App\Modules\Ai\Application\Support\GroundedAnswerParser;
use App\Modules\Ai\Application\Support\OrganizerFaqMatcher;
use App\Modules\Ai\Application\Support\PromptBuilder;
use App\Modules\Ai\Contracts\KnowledgeRetriever;
use App\Modules\Ai\Contracts\LlmProvider;
use App\Modules\Ai\Domain\AiAnswer;
use App\Modules\Ai\Domain\AiProviderException;
use App\Modules\Ai\Domain\ContextChunk;
use App\Modules\Ai\Infrastructure\Persistence\Models\AssistantConversation;
use App\Modules\Ai\Infrastructure\Persistence\Models\AssistantTurn;
use App\Modules\Ai\Infrastructure\Persistence\Models\EventAssistantSettings;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use Illuminate\Support\Str;

final class AnswerEventQuestion
{
    public function __construct(
        private readonly LlmProvider $llmProvider,
        private readonly KnowledgeRetriever $retriever,
        private readonly PromptBuilder $promptBuilder,
        private readonly GroundedAnswerParser $answerParser,
        private readonly AiLimiter $limiter,
        private readonly OrganizerFaqMatcher $faqMatcher,
    ) {}

    public function execute(
        int $tenantId,
        int $eventId,
        string $message,
        string $locale,
        string $visitorHash,
        ?string $conversationPublicId = null,
    ): AiAnswer {
        $settings = EventAssistantSettings::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->first();

        if ($settings === null || ! $settings->enabled) {
            return $this->recordTurn($tenantId, $eventId, $message, AiAnswer::unavailable($locale), $visitorHash, $conversationPublicId);
        }

        if ($settings->index_status !== 'ready') {
            // FAQ exact match can still answer before the index is ready.
            $faq = $this->faqMatcher->match($tenantId, $eventId, $message, $locale);
            if ($faq !== null) {
                return $this->recordTurn(
                    $tenantId,
                    $eventId,
                    $message,
                    AiAnswer::answered(
                        answer: $faq->answerFor($locale),
                        citations: [[
                            'source_type' => 'organizer_faq',
                            'source_id' => 'faq:'.$faq->id,
                            'title' => $faq->questionFor($locale),
                        ]],
                        locale: $locale,
                        providerKey: 'faq_match',
                        latencyMs: 0,
                        promptTokens: 0,
                        completionTokens: 0,
                    ),
                    $visitorHash,
                    $conversationPublicId,
                );
            }

            return $this->recordTurn($tenantId, $eventId, $message, AiAnswer::unavailable($locale), $visitorHash, $conversationPublicId);
        }

        if (! $this->llmProvider->isAvailable()) {
            return $this->recordTurn($tenantId, $eventId, $message, AiAnswer::unavailable($locale), $visitorHash, $conversationPublicId);
        }

        if (! $this->limiter->checkVisitorThrottle($visitorHash)) {
            return $this->recordTurn($tenantId, $eventId, $message, AiAnswer::throttled($locale), $visitorHash, $conversationPublicId);
        }

        if (! $this->limiter->checkEventDailyCeiling($tenantId, $eventId, $settings->daily_question_limit)) {
            return $this->recordTurn($tenantId, $eventId, $message, AiAnswer::throttled($locale), $visitorHash, $conversationPublicId);
        }

        $this->limiter->incrementVisitorThrottle($visitorHash);

        if ($this->answerParser->isRefusalAttempt($message)) {
            return $this->recordTurn($tenantId, $eventId, $message, AiAnswer::refused($locale), $visitorHash, $conversationPublicId);
        }

        if ($this->answerParser->isOutOfScope($message)) {
            return $this->recordTurn($tenantId, $eventId, $message, AiAnswer::refused($locale), $visitorHash, $conversationPublicId);
        }

        $faq = $this->faqMatcher->match($tenantId, $eventId, $message, $locale);
        if ($faq !== null) {
            return $this->recordTurn(
                $tenantId,
                $eventId,
                $message,
                AiAnswer::answered(
                    answer: $faq->answerFor($locale),
                    citations: [[
                        'source_type' => 'organizer_faq',
                        'source_id' => 'faq:'.$faq->id,
                        'title' => $faq->questionFor($locale),
                    ]],
                    locale: $locale,
                    providerKey: 'faq_match',
                    latencyMs: 0,
                    promptTokens: 0,
                    completionTokens: 0,
                ),
                $visitorHash,
                $conversationPublicId,
            );
        }

        $chunks = $this->retriever->retrieve(
            $tenantId,
            $eventId,
            $settings->index_version,
            $message,
            $locale,
            (int) config('ai.assistant.retrieval_top_k', 5),
            $this->limiter->maxContextChars(),
        );

        if ($chunks === []) {
            return $this->recordTurn($tenantId, $eventId, $message, AiAnswer::unanswered($locale), $visitorHash, $conversationPublicId);
        }

        $event = Event::query()->where('tenant_id', $tenantId)->find($eventId);
        $eventName = $locale === 'ar' ? ($event?->name_ar ?? 'Event') : ($event?->name_en ?? 'Event');

        $request = $this->promptBuilder->buildAssistantRequest(
            $message,
            $chunks,
            $locale,
            $eventName,
            $settings->fallback_action,
            $settings->fallback_contact_email,
        );

        try {
            $result = $this->llmProvider->complete($request);

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

            $answer = $this->answerParser->parse($result, $contextChunks, $locale);

        } catch (AiProviderException) {
            $answer = AiAnswer::unavailable($locale);
        }

        return $this->recordTurn($tenantId, $eventId, $message, $answer, $visitorHash, $conversationPublicId);
    }

    private function recordTurn(
        int $tenantId,
        int $eventId,
        string $question,
        AiAnswer $answer,
        string $visitorHash,
        ?string $conversationPublicId,
    ): AiAnswer {
        $conversation = $conversationPublicId !== null
            ? AssistantConversation::query()
                ->where('tenant_id', $tenantId)
                ->where('public_id', $conversationPublicId)
                ->first()
            : null;

        if ($conversation === null) {
            $retentionDays = (int) config('ai.assistant.transcript_retention_days', 90);
            $conversation = AssistantConversation::create([
                'tenant_id' => $tenantId,
                'event_id' => $eventId,
                'public_id' => Str::uuid()->toString(),
                'locale' => $answer->locale,
                'visitor_hash' => $visitorHash,
                'started_at' => now(),
                'last_activity_at' => now(),
                'turn_count' => 0,
                'purge_after' => now()->addDays($retentionDays),
            ]);
        }

        AssistantTurn::create([
            'tenant_id' => $tenantId,
            'event_id' => $eventId,
            'conversation_id' => $conversation->id,
            'question' => $question,
            'answer' => $answer->answer,
            'outcome' => $answer->outcome->value,
            'citations' => $answer->citations !== [] ? $answer->citations : null,
            'provider_key' => $answer->providerKey,
            'latency_ms' => $answer->latencyMs,
            'prompt_tokens' => $answer->promptTokens,
            'completion_tokens' => $answer->completionTokens,
        ]);

        $conversation->increment('turn_count');
        $conversation->update(['last_activity_at' => now()]);

        return new AiAnswer(
            outcome: $answer->outcome,
            answer: $answer->answer,
            citations: $answer->citations,
            locale: $answer->locale,
            providerKey: $answer->providerKey,
            latencyMs: $answer->latencyMs,
            promptTokens: $answer->promptTokens,
            completionTokens: $answer->completionTokens,
        );
    }

    public function getConversationPublicId(int $tenantId, int $eventId, string $visitorHash): ?string
    {
        $conversation = AssistantConversation::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('visitor_hash', $visitorHash)
            ->orderByDesc('last_activity_at')
            ->first();

        return $conversation?->public_id;
    }
}
