<?php

namespace App\Modules\Ai\Application\Support;

use App\Modules\Ai\Infrastructure\Persistence\Models\EventAssistantFaq;

final class OrganizerFaqMatcher
{
    /**
     * Find an active FAQ whose question closely matches the visitor/organizer question.
     */
    public function match(int $tenantId, int $eventId, string $question, string $locale): ?EventAssistantFaq
    {
        $normalizedQuestion = $this->normalize($question);
        if ($normalizedQuestion === '') {
            return null;
        }

        $faqs = EventAssistantFaq::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $best = null;
        $bestScore = 0.0;

        foreach ($faqs as $faq) {
            foreach ([$faq->question_en, $faq->question_ar] as $candidate) {
                $score = $this->similarity($normalizedQuestion, $this->normalize($candidate));
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = $faq;
                }
            }
        }

        // Strong match: exact/near-exact or high token overlap.
        if ($best !== null && $bestScore >= 0.82) {
            return $best;
        }

        return null;
    }

    public function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    public function similarity(string $a, string $b): float
    {
        if ($a === '' || $b === '') {
            return 0.0;
        }

        if ($a === $b) {
            return 1.0;
        }

        if (str_contains($a, $b) || str_contains($b, $a)) {
            $shorter = min(mb_strlen($a), mb_strlen($b));
            $longer = max(mb_strlen($a), mb_strlen($b));

            return $longer > 0 ? ($shorter / $longer) + 0.15 : 0.0;
        }

        similar_text($a, $b, $percent);

        return max(0.0, min(1.0, $percent / 100));
    }
}
