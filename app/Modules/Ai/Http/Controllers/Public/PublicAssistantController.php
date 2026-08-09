<?php

namespace App\Modules\Ai\Http\Controllers\Public;

use App\Modules\Ai\Application\Actions\AnswerEventQuestion;
use App\Modules\Ai\Http\Requests\AskAssistantRequest;
use App\Modules\Ai\Infrastructure\Persistence\Models\EventAssistantSettings;
use App\Modules\Events\Domain\Context\PublicEventContextStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class PublicAssistantController extends Controller
{
    public function ask(
        AskAssistantRequest $request,
        AnswerEventQuestion $action,
        PublicEventContextStore $eventContexts,
    ): JsonResponse {
        $context = $eventContexts->currentOrNull();

        if ($context === null) {
            return response()->json([
                'type' => 'https://docs.zonetec.example/problems/not_found',
                'title' => 'Not Found',
                'status' => 404,
            ], 404);
        }

        $tenantId = (int) $context->tenantId;
        $eventId = (int) $context->eventId;

        $settings = EventAssistantSettings::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->first();

        if ($settings === null || ! $settings->enabled) {
            return response()->json([
                'type' => 'https://docs.zonetec.example/problems/assistant_unavailable',
                'title' => 'Assistant Unavailable',
                'status' => 503,
                'code' => 'ai.assistant_disabled',
            ], 503);
        }

        $visitorHash = $this->computeVisitorHash($request);

        $answer = $action->execute(
            $tenantId,
            $eventId,
            $request->validated('message'),
            $request->validated('locale'),
            $visitorHash,
            $request->validated('conversation_id'),
        );

        $response = [
            'data' => [
                'conversation_id' => $request->validated('conversation_id') ?? $action->getConversationPublicId($tenantId, $eventId, $visitorHash),
                'outcome' => $answer->outcome->value,
                'locale' => $answer->locale,
            ],
        ];

        if ($answer->answer !== null) {
            $response['data']['answer'] = $answer->answer;
        }

        if ($answer->citations !== []) {
            $response['data']['citations'] = $answer->citations;
        }

        if ($answer->outcome->value === 'throttled') {
            return response()->json(array_merge($response, [
                'type' => 'https://docs.zonetec.example/problems/rate_limited',
                'title' => 'Rate Limited',
                'status' => 429,
            ]), 429);
        }

        if ($answer->outcome->value === 'unavailable') {
            return response()->json(array_merge($response, [
                'type' => 'https://docs.zonetec.example/problems/assistant_unavailable',
                'title' => 'Assistant Unavailable',
                'status' => 503,
            ]), 503);
        }

        if (in_array($answer->outcome->value, ['unanswered', 'refused'], true)) {
            $response['data']['fallback'] = $this->buildFallback($settings, $answer->locale);
        }

        return response()->json($response);
    }

    private function computeVisitorHash(Request $request): string
    {
        $salt = config('app.key', 'zoon');

        return hash('sha256', implode('|', [
            $salt,
            $request->ip(),
            $request->userAgent(),
        ]));
    }

    private function buildFallback(EventAssistantSettings $settings, string $locale): array
    {
        $fallback = ['action' => $settings->fallback_action];

        if ($settings->fallback_action === 'contact' && $settings->fallback_contact_email) {
            $fallback['email'] = $settings->fallback_contact_email;
        }

        $fallback['message'] = $locale === 'ar'
            ? __('ai.fallback_message_ar')
            : __('ai.fallback_message_en');

        return $fallback;
    }
}
