<?php

namespace App\Modules\Ai\Http\Controllers;

use App\Modules\Ai\Application\Actions\HandlePlatformChat;
use App\Modules\Ai\Domain\AiProviderException;
use App\Modules\Ai\Http\Requests\PlatformChatRequest;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

final class PlatformChatController extends Controller
{
    public function __invoke(
        PlatformChatRequest $request,
        HandlePlatformChat $action,
    ): JsonResponse {
        $context = app(TenantContextStore::class)->current();
        $tenantId = (int) $context->tenant->id;

        try {
            $result = $action->execute(
                $tenantId,
                $request->validated('message'),
                $request->validated('locale', 'en'),
                $request->validated('history', []),
            );
        } catch (AiProviderException) {
            return response()->json([
                'type' => 'https://docs.zonetec.example/problems/ai_unavailable',
                'title' => 'AI Provider Unavailable',
                'status' => 503,
                'code' => 'ai.provider_unavailable',
            ], 503);
        }

        return response()->json([
            'data' => [
                'answer' => $result->answer,
                'handler' => $result->handler,
                'structured' => $result->structured,
                'provider_key' => $result->providerKey,
                'latency_ms' => $result->latencyMs,
            ],
        ]);
    }
}
