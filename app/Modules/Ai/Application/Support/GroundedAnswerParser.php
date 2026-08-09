<?php

namespace App\Modules\Ai\Application\Support;

use App\Modules\Ai\Domain\AiAnswer;
use App\Modules\Ai\Domain\AiCompletionResult;
use App\Modules\Ai\Domain\ContextChunk;

final class GroundedAnswerParser
{
    /**
     * @param  list<ContextChunk>  $providedChunks
     */
    public function parse(
        AiCompletionResult $result,
        array $providedChunks,
        string $locale,
    ): AiAnswer {
        $providedNumbers = array_map(fn (ContextChunk $c) => $c->number, $providedChunks);

        $validCitations = array_filter(
            $result->citedChunkNumbers,
            fn (int $n) => in_array($n, $providedNumbers, true),
        );

        if ($validCitations === []) {
            return AiAnswer::unanswered($locale);
        }

        $citations = [];
        foreach ($validCitations as $number) {
            foreach ($providedChunks as $chunk) {
                if ($chunk->number === $number) {
                    $citations[] = [
                        'source_type' => $chunk->sourceType,
                        'source_id' => $chunk->sourceId,
                        'title' => $chunk->title,
                    ];
                    break;
                }
            }
        }

        return AiAnswer::answered(
            answer: $result->text,
            citations: $citations,
            locale: $locale,
            providerKey: $result->providerKey,
            latencyMs: $result->latencyMs,
            promptTokens: $result->promptTokens,
            completionTokens: $result->completionTokens,
        );
    }

    public function isRefusalAttempt(string $message): bool
    {
        $refusalPatterns = [
            '/ignore\s+(all\s+)?(previous|prior|above)/i',
            '/disregard\s+(all\s+)?(previous|prior|above)/i',
            '/forget\s+(all\s+)?(previous|prior|above)/i',
            '/you\s+are\s+now/i',
            '/act\s+as\s+(a|an)/i',
            '/pretend\s+(to\s+be|you)/i',
            '/system\s+prompt/i',
            '/reveal\s+(your|the)\s+instructions/i',
            '/what\s+are\s+your\s+instructions/i',
            '/jailbreak/i',
            '/DAN\s+mode/i',
        ];

        foreach ($refusalPatterns as $pattern) {
            if (preg_match($pattern, $message)) {
                return true;
            }
        }

        return false;
    }

    public function isOutOfScope(string $message): bool
    {
        $outOfScopePatterns = [
            '/attendee.*(email|phone|address|personal)/i',
            '/list\s+(all\s+)?attendees/i',
            '/personal\s+(data|information)/i',
            '/other\s+event/i',
            '/internal\s+(operations?|system)/i',
        ];

        foreach ($outOfScopePatterns as $pattern) {
            if (preg_match($pattern, $message)) {
                return true;
            }
        }

        return false;
    }
}
