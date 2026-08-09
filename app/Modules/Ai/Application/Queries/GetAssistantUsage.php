<?php

namespace App\Modules\Ai\Application\Queries;

use App\Modules\Ai\Infrastructure\Persistence\Models\AssistantTurn;

final class GetAssistantUsage
{
    /**
     * @return array{
     *     totals: array{questions: int, answered: int, unanswered: int, refused: int, unavailable: int, throttled: int},
     *     answer_rate: float,
     *     avg_latency_ms: int,
     *     tokens: array{prompt: int, completion: int},
     *     unanswered_questions: list<array{question: string, count: int, last_asked_at: string}>
     * }
     */
    public function execute(int $tenantId, int $eventId): array
    {
        $outcomes = AssistantTurn::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->selectRaw('outcome, COUNT(*) as count')
            ->groupBy('outcome')
            ->pluck('count', 'outcome')
            ->toArray();

        $totals = [
            'questions' => array_sum($outcomes),
            'answered' => $outcomes['answered'] ?? 0,
            'unanswered' => $outcomes['unanswered'] ?? 0,
            'refused' => $outcomes['refused'] ?? 0,
            'unavailable' => $outcomes['unavailable'] ?? 0,
            'throttled' => $outcomes['throttled'] ?? 0,
        ];

        $answerRate = $totals['questions'] > 0
            ? round($totals['answered'] / $totals['questions'], 2)
            : 0.0;

        $avgLatency = (int) AssistantTurn::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->whereNotNull('latency_ms')
            ->avg('latency_ms');

        $tokens = AssistantTurn::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->selectRaw('COALESCE(SUM(prompt_tokens), 0) as prompt, COALESCE(SUM(completion_tokens), 0) as completion')
            ->first();

        $unansweredQuestions = AssistantTurn::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('outcome', 'unanswered')
            ->selectRaw('question, COUNT(*) as count, MAX(created_at) as last_asked_at')
            ->groupBy('question')
            ->orderByDesc('count')
            ->limit(20)
            ->get()
            ->map(fn ($row) => [
                'question' => $row->question,
                'count' => (int) $row->count,
                'last_asked_at' => $row->last_asked_at,
            ])
            ->toArray();

        return [
            'totals' => $totals,
            'answer_rate' => $answerRate,
            'avg_latency_ms' => $avgLatency,
            'tokens' => [
                'prompt' => (int) $tokens->prompt,
                'completion' => (int) $tokens->completion,
            ],
            'unanswered_questions' => $unansweredQuestions,
        ];
    }
}
