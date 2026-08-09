<?php

namespace App\Modules\Ai\Application\Actions;

use App\Modules\Ai\Application\Analytics\PlatformAnalyticsTools;
use App\Modules\Ai\Application\Chat\PlatformRagContextBuilder;
use App\Modules\Ai\Application\Support\FunctionCallingChatService;
use App\Modules\Ai\Domain\AiProviderException;
use App\Modules\Ai\Domain\ChatCompletionResult;

final class HandlePlatformChat
{
    public function __construct(
        private readonly PlatformRagContextBuilder $ragContextBuilder,
        private readonly FunctionCallingChatService $functionCallingChat,
    ) {}

    /**
     * @param  list<array{role: string, content: string}>  $history
     */
    public function execute(
        int $tenantId,
        string $message,
        string $locale,
        array $history = [],
    ): ChatCompletionResult {
        $message = trim($message);
        if ($message === '') {
            return new ChatCompletionResult(
                answer: $locale === 'ar' ? 'يرجى إدخال سؤال.' : 'Please enter a question.',
                handler: 'validation',
            );
        }

        if ($this->isOutOfScope($message)) {
            return new ChatCompletionResult(
                answer: $locale === 'ar'
                    ? 'لا يمكنني الإجابة على أسئلة تتضمن بيانات شخصية للحضور.'
                    : 'I cannot answer questions that request personal attendee data.',
                handler: 'refused',
            );
        }

        $ragContext = $this->ragContextBuilder->build($tenantId, $locale);
        $analyticsTools = new PlatformAnalyticsTools($tenantId);

        try {
            return $this->functionCallingChat->chat(
                message: $message,
                locale: $locale,
                ragContext: $ragContext,
                tools: $analyticsTools,
                history: $history,
            );
        } catch (AiProviderException) {
            return $this->fallbackAnswer($tenantId, $message, $locale, $ragContext, $analyticsTools);
        }
    }

    private function isOutOfScope(string $message): bool
    {
        $patterns = [
            '/attendee.*(name|email|phone|address)/i',
            '/list\s+(of\s+)?(attendees|registrants)/i',
            '/personal\s+(data|information)/i',
            '/specific\s+(person|attendee)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message)) {
                return true;
            }
        }

        return false;
    }

    private function fallbackAnswer(
        int $tenantId,
        string $message,
        string $locale,
        string $ragContext,
        PlatformAnalyticsTools $tools,
    ): ChatCompletionResult {
        if ($this->looksLikeAnalytics($message)) {
            $result = $this->executeHeuristicAnalytics($message, $tools);
            if ($result !== null) {
                return $result;
            }
        }

        return new ChatCompletionResult(
            answer: $locale === 'ar'
                ? "بناءً على البيانات المتاحة:\n{$ragContext}"
                : "Based on available data:\n{$ragContext}",
            handler: 'rag',
        );
    }

    private function looksLikeAnalytics(string $message): bool
    {
        return (bool) preg_match(
            '/\b(how many|count|total|popular|top|sold|tickets|attendees|events|bookings|highest)\b/i',
            $message,
        );
    }

    private function executeHeuristicAnalytics(string $message, PlatformAnalyticsTools $tools): ?ChatCompletionResult
    {
        $city = null;
        if (preg_match('/\bin\s+([A-Za-z\u0600-\u06FF\s]+?)(?:\s+events?)?[\?\.]?$/iu', $message, $matches)) {
            $city = trim($matches[1]);
        }

        if (preg_match('/attendees?/i', $message)) {
            $data = $tools->getAttendeesCount($city);

            return new ChatCompletionResult(
                answer: "{$data['label']}: {$data['value']}",
                handler: 'analytics',
                structured: [$data],
            );
        }

        if (preg_match('/events?/i', $message) && preg_match('/how many|count/i', $message)) {
            $data = $tools->getEventsCount($city);

            return new ChatCompletionResult(
                answer: "{$data['label']}: {$data['value']}",
                handler: 'analytics',
                structured: [$data],
            );
        }

        if (preg_match('/popular|top\s+event/i', $message)) {
            $data = $tools->getTopEvent();

            return new ChatCompletionResult(
                answer: "{$data['label']}: {$data['event_name']} ({$data['registrations']} registrations)",
                handler: 'analytics',
                structured: [$data],
            );
        }

        if (preg_match('/tickets?\s+sold|sold\s+this\s+(week|month|today)/i', $message, $matches)) {
            $range = match (strtolower($matches[1] ?? '')) {
                'week' => 'week',
                'month' => 'month',
                'today' => 'today',
                default => 'all_time',
            };
            $data = $tools->getTicketsSold($range);

            return new ChatCompletionResult(
                answer: "{$data['label']}: {$data['value']}",
                handler: 'analytics',
                structured: [$data],
            );
        }

        return null;
    }
}
