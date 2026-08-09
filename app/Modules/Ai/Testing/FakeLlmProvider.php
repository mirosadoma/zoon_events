<?php

namespace App\Modules\Ai\Testing;

use App\Modules\Ai\Contracts\LlmProvider;
use App\Modules\Ai\Domain\AiCompletionRequest;
use App\Modules\Ai\Domain\AiCompletionResult;
use App\Modules\Ai\Domain\AiPurpose;

final class FakeLlmProvider implements LlmProvider
{
    private bool $available = true;

    /** @var list<AiCompletionResult> */
    private array $queuedResults = [];

    /** @var list<AiCompletionRequest> */
    public array $requests = [];

    public function key(): string
    {
        return 'fake';
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function setAvailable(bool $available): void
    {
        $this->available = $available;
    }

    public function pushResult(AiCompletionResult $result): void
    {
        $this->queuedResults[] = $result;
    }

    public function complete(AiCompletionRequest $request): AiCompletionResult
    {
        $this->requests[] = $request;

        if ($this->queuedResults !== []) {
            return array_shift($this->queuedResults);
        }

        return $this->generateDeterministicAnswer($request);
    }

    private function generateDeterministicAnswer(AiCompletionRequest $request): AiCompletionResult
    {
        $startTime = hrtime(true);
        $citedNumbers = [];

        if ($request->purpose === AiPurpose::InsightSummary || $request->purpose === AiPurpose::InsightAnswer) {
            $answer = $this->generateInsightAnswer($request);
        } elseif ($request->contextChunks === []) {
            $answer = $request->locale === 'ar'
                ? 'عذراً، لا تتوفر لدي معلومات كافية للإجابة على هذا السؤال.'
                : 'I don\'t have enough information to answer this question.';
        } else {
            $answerParts = [];

            foreach ($request->contextChunks as $chunk) {
                $citedNumbers[] = $chunk->number;
                $summary = mb_substr($chunk->text, 0, 100);
                if (mb_strlen($chunk->text) > 100) {
                    $summary .= '...';
                }
                $answerParts[] = "From [{$chunk->number}]: {$summary}";
            }

            $citedNumbers = array_slice($citedNumbers, 0, 3);

            $citations = implode(' ', array_map(fn (int $n) => "[{$n}]", $citedNumbers));
            $answer = $request->locale === 'ar'
                ? 'بناءً على المعلومات المتوفرة: '.implode(' ', $answerParts)." {$citations}"
                : 'Based on the available information: '.implode(' ', $answerParts)." {$citations}";
        }

        return new AiCompletionResult(
            text: $answer,
            citedChunkNumbers: $citedNumbers,
            promptTokens: $this->estimateTokens($request->systemPrompt.$request->userMessage),
            completionTokens: $this->estimateTokens($answer),
            providerKey: $this->key(),
            latencyMs: (int) ((hrtime(true) - $startTime) / 1_000_000),
        );
    }

    private function generateInsightAnswer(AiCompletionRequest $request): string
    {
        $registered = 0;
        $checkedIn = 0;
        if (preg_match('/"registered_count"\s*:\s*(\d+)/', $request->userMessage, $matches)) {
            $registered = (int) $matches[1];
        }
        if (preg_match('/"checked_in_count"\s*:\s*(\d+)/', $request->userMessage, $matches)) {
            $checkedIn = (int) $matches[1];
        }

        $rate = $registered > 0 ? round(($checkedIn / $registered) * 100, 1) : 0.0;

        if ($request->purpose === AiPurpose::InsightAnswer) {
            return $request->locale === 'ar'
                ? "بناءً على المقاييس المتاحة: يوجد {$registered} تسجيل و{$checkedIn} حضور (معدل حضور {$rate}%)."
                : "Based on the available metrics: there are {$registered} registrations and {$checkedIn} check-ins (attendance rate {$rate}%).";
        }

        if ($request->locale === 'ar') {
            return "ملخص التحليل: الحدث سجّل {$registered} مشاركاً وتم تسجيل حضور {$checkedIn} (معدل {$rate}%).\n"
                ."- اتجاه: حجم التسجيل الحالي {$registered}.\n"
                ."- مخاطر: راقب معدل الحضور إذا انخفض عن التوقعات.\n"
                .'- إجراء: راجع حملات التذكير للمسجّلين الذين لم يحضروا بعد.';
        }

        return "Insight summary: the event has {$registered} registrations and {$checkedIn} check-ins ({$rate}% attendance).\n"
            ."- Trend: registration volume is currently {$registered}.\n"
            ."- Risk: monitor attendance rate if it falls below expectations.\n"
            .'- Action: consider reminder campaigns for registered attendees who have not checked in.';
    }

    private function estimateTokens(string $text): int
    {
        return (int) ceil(mb_strlen($text) / 4);
    }

    public function reset(): void
    {
        $this->available = true;
        $this->queuedResults = [];
        $this->requests = [];
    }
}
