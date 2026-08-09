<?php

namespace App\Modules\Ai\Application\Support;

use App\Modules\Ai\Application\Analytics\PlatformAnalyticsTools;
use App\Modules\Ai\Contracts\AiSecretLoader;
use App\Modules\Ai\Contracts\LlmProvider;
use App\Modules\Ai\Domain\AiProviderException;
use App\Modules\Ai\Domain\ChatCompletionResult;
use App\Modules\Ai\Infrastructure\Adapters\OpenAiCompatibleClient;
use App\Modules\Ai\Testing\FakeLlmProvider;

final class FunctionCallingChatService
{
    private const MAX_TOOL_ROUNDS = 3;

    public function __construct(
        private readonly LlmProvider $llmProvider,
        private readonly ?OpenAiCompatibleClient $networkClient = null,
        private readonly ?AiSecretLoader $secretLoader = null,
    ) {}

    /**
     * @param  list<array{role: string, content: string}>  $history
     */
    public function chat(
        string $message,
        string $locale,
        string $ragContext,
        PlatformAnalyticsTools $tools,
        array $history = [],
    ): ChatCompletionResult {
        if ($this->llmProvider instanceof FakeLlmProvider) {
            return $this->fakeChat($message, $locale, $ragContext, $tools);
        }

        if ($this->networkClient === null || ! $this->llmProvider->isAvailable()) {
            throw AiProviderException::notConfigured();
        }

        $systemPrompt = $this->buildSystemPrompt($locale, $ragContext);
        $messages = [['role' => 'system', 'content' => $systemPrompt]];

        foreach (array_slice($history, -6) as $turn) {
            $role = $turn['role'] ?? 'user';
            $content = trim((string) ($turn['content'] ?? ''));
            if ($content !== '' && in_array($role, ['user', 'assistant'], true)) {
                $messages[] = ['role' => $role, 'content' => $content];
            }
        }

        $messages[] = ['role' => 'user', 'content' => $message];

        $toolDefinitions = $tools->toolDefinitions();
        $structured = [];
        $handler = 'rag';
        $startTime = hrtime(true);

        for ($round = 0; $round < self::MAX_TOOL_ROUNDS; $round++) {
            $response = $this->networkClient->chatCompletions($messages, $toolDefinitions);
            $choice = $response['choices'][0] ?? [];
            $assistantMessage = $choice['message'] ?? [];
            $toolCalls = $assistantMessage['tool_calls'] ?? [];

            if ($toolCalls === []) {
                $text = trim((string) ($assistantMessage['content'] ?? ''));

                return new ChatCompletionResult(
                    answer: $text !== '' ? $text : ($locale === 'ar' ? 'لا توجد إجابة.' : 'No answer available.'),
                    handler: $handler,
                    structured: $structured,
                    providerKey: $this->llmProvider->key(),
                    latencyMs: (int) ((hrtime(true) - $startTime) / 1_000_000),
                );
            }

            $handler = 'analytics';
            $messages[] = $assistantMessage;

            foreach ($toolCalls as $toolCall) {
                $functionName = (string) ($toolCall['function']['name'] ?? '');
                $arguments = json_decode((string) ($toolCall['function']['arguments'] ?? '{}'), true);
                if (! is_array($arguments)) {
                    $arguments = [];
                }

                $result = $tools->execute($functionName, $arguments);
                $structured[] = $result;

                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $toolCall['id'] ?? uniqid('tool_', true),
                    'content' => json_encode($result),
                ];
            }
        }

        throw AiProviderException::providerError('Tool calling exceeded maximum rounds.');
    }

    private function fakeChat(
        string $message,
        string $locale,
        string $ragContext,
        PlatformAnalyticsTools $tools,
    ): ChatCompletionResult {
        $lower = mb_strtolower($message);

        if (preg_match('/attendees?/i', $message)) {
            $city = null;
            if (preg_match('/cairo|القاهرة/i', $message)) {
                $city = 'Cairo';
            }
            $data = $tools->getAttendeesCount($city);

            return new ChatCompletionResult(
                answer: $locale === 'ar'
                    ? 'عدد الحضور'.($city ? " في {$city}" : '').": {$data['value']}"
                    : 'Attendee count'.($city ? " in {$city}" : '').": {$data['value']}",
                handler: 'analytics',
                structured: [$data],
                providerKey: 'fake',
            );
        }

        if (preg_match('/popular|top event/i', $lower)) {
            $data = $tools->getTopEvent();

            return new ChatCompletionResult(
                answer: $locale === 'ar'
                    ? "الحدث الأكثر شعبية: {$data['event_name']} ({$data['registrations']} تسجيل)"
                    : "Most popular event: {$data['event_name']} ({$data['registrations']} registrations)",
                handler: 'analytics',
                structured: [$data],
                providerKey: 'fake',
            );
        }

        if (preg_match('/tickets?\s+sold|sold/i', $lower)) {
            $data = $tools->getTicketsSold('week');

            return new ChatCompletionResult(
                answer: $locale === 'ar'
                    ? "التذاكر المباعة هذا الأسبوع: {$data['value']}"
                    : "Tickets sold this week: {$data['value']}",
                handler: 'analytics',
                structured: [$data],
                providerKey: 'fake',
            );
        }

        return new ChatCompletionResult(
            answer: $locale === 'ar'
                ? "بناءً على البيانات المتاحة:\n{$ragContext}"
                : "Based on available data:\n{$ragContext}",
            handler: 'rag',
            providerKey: 'fake',
        );
    }

    private function buildSystemPrompt(string $locale, string $ragContext): string
    {
        $lang = $locale === 'ar' ? 'Respond in Arabic.' : 'Respond in English.';

        return <<<PROMPT
You are an AI assistant for an event management platform.

Rules:
- {$lang}
- Use database data when available via function tools.
- For statistics and numeric questions, always call the appropriate function. Never guess numbers.
- For general questions about events, venues, or tickets, use the provided context below.
- Be concise and clear.
- Recommend relevant events when possible.
- Never expose personal attendee data (names, emails, phones).

Platform knowledge context:
{$ragContext}
PROMPT;
    }
}
