<?php

namespace App\Modules\Ai\Application\Support;

use App\Modules\Ai\Infrastructure\Persistence\Models\AssistantTurn;
use Illuminate\Support\Facades\Cache;

final class AiLimiter
{
    public function checkVisitorThrottle(string $visitorHash): bool
    {
        $key = "ai:visitor_throttle:{$visitorHash}";
        $limit = (int) config('ai.assistant.visitor_questions_per_minute', 6);
        $count = (int) Cache::get($key, 0);

        return $count < $limit;
    }

    public function incrementVisitorThrottle(string $visitorHash): void
    {
        $key = "ai:visitor_throttle:{$visitorHash}";
        $count = Cache::increment($key);
        if ($count === 1) {
            Cache::put($key, 1, 60);
        }
    }

    public function checkEventDailyCeiling(int $tenantId, int $eventId, ?int $customLimit = null): bool
    {
        $limit = $customLimit ?? (int) config('ai.assistant.event_questions_per_day', 500);

        $today = now()->startOfDay();
        $count = AssistantTurn::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('created_at', '>=', $today)
            ->count();

        return $count < $limit;
    }

    public function maxContextChars(): int
    {
        return (int) config('ai.max_context_chars', 6000);
    }

    public function maxOutputTokens(): int
    {
        return (int) config('ai.max_output_tokens', 600);
    }

    public function maxQuestionChars(): int
    {
        return (int) config('ai.assistant.max_question_chars', 1000);
    }

    public function transcriptRetentionDays(): int
    {
        return (int) config('ai.assistant.transcript_retention_days', 90);
    }

    public function insightCacheMinutes(): int
    {
        return (int) config('ai.insights.cache_minutes', 15);
    }

    public function minBucketSize(): int
    {
        return (int) config('ai.insights.min_bucket_size', 5);
    }
}
