<?php

namespace App\Modules\Ai\Http\Controllers;

use App\Modules\Ai\Application\Jobs\RebuildEventKnowledgeIndexJob;
use App\Modules\Ai\Application\Queries\GetAssistantUsage;
use App\Modules\Ai\Contracts\LlmProvider;
use App\Modules\Ai\Http\Requests\UpdateAssistantConfigRequest;
use App\Modules\Ai\Infrastructure\Persistence\Models\AssistantConversation;
use App\Modules\Ai\Infrastructure\Persistence\Models\EventAssistantSettings;
use App\Modules\Audit\Contracts\AuditWriter;
use App\Modules\Shared\Domain\Context\RequestContextStore;
use App\Modules\Tenancy\Contracts\Queue\TenantJobContext;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class AssistantConfigController extends Controller
{
    public function show(Request $request, LlmProvider $llmProvider): JsonResponse
    {
        $context = app(TenantContextStore::class)->current();
        $tenantId = (int) $context->tenant->id;
        $eventId = (int) $request->route('event_id');

        $settings = EventAssistantSettings::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->first();

        if ($settings === null) {
            $settings = EventAssistantSettings::create([
                'tenant_id' => $tenantId,
                'event_id' => $eventId,
                'daily_question_limit' => (int) config('ai.assistant.event_questions_per_day', 500),
            ]);
        }

        return response()->json([
            'data' => [
                'enabled' => $settings->enabled,
                'display_name' => [
                    'en' => $settings->display_name_en,
                    'ar' => $settings->display_name_ar,
                ],
                'greeting' => [
                    'en' => $settings->greeting_en,
                    'ar' => $settings->greeting_ar,
                ],
                'fallback_action' => $settings->fallback_action,
                'fallback_contact_email' => $settings->fallback_contact_email,
                'daily_question_limit' => $settings->daily_question_limit,
                'index' => [
                    'status' => $settings->index_status,
                    'version' => $settings->index_version,
                    'indexed_at' => $settings->indexed_at?->toIso8601String(),
                    'chunk_count' => $settings->chunk_count,
                    'error_code' => $settings->index_error_code,
                ],
                'provider' => [
                    'available' => $llmProvider->isAvailable(),
                    'reason' => $llmProvider->isAvailable() ? null : 'network_disabled',
                ],
            ],
        ]);
    }

    public function update(
        UpdateAssistantConfigRequest $request,
        AuditWriter $auditWriter,
    ): JsonResponse {
        $context = app(TenantContextStore::class)->current();
        $tenantId = (int) $context->tenant->id;
        $eventId = (int) $request->route('event_id');

        $settings = EventAssistantSettings::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->first();

        if ($settings === null) {
            $settings = EventAssistantSettings::create([
                'tenant_id' => $tenantId,
                'event_id' => $eventId,
            ]);
        }

        $wasEnabled = $settings->enabled;

        $data = $request->validated();

        $updateData = [];
        if (isset($data['enabled'])) {
            $updateData['enabled'] = $data['enabled'];
        }
        if (isset($data['display_name']['en'])) {
            $updateData['display_name_en'] = $data['display_name']['en'];
        }
        if (isset($data['display_name']['ar'])) {
            $updateData['display_name_ar'] = $data['display_name']['ar'];
        }
        if (isset($data['greeting']['en'])) {
            $updateData['greeting_en'] = $data['greeting']['en'];
        }
        if (isset($data['greeting']['ar'])) {
            $updateData['greeting_ar'] = $data['greeting']['ar'];
        }
        if (isset($data['fallback_action'])) {
            $updateData['fallback_action'] = $data['fallback_action'];
        }
        if (array_key_exists('fallback_contact_email', $data)) {
            $updateData['fallback_contact_email'] = $data['fallback_contact_email'];
        }
        if (isset($data['daily_question_limit'])) {
            $updateData['daily_question_limit'] = $data['daily_question_limit'];
        }

        $settings->update($updateData);

        $auditWriter->write(
            scope: 'tenant',
            tenantId: (string) $tenantId,
            action: 'event_assistant.configured',
            outcome: 'succeeded',
            actor: $context->actor,
            targetType: 'event',
            targetId: (string) $eventId,
            metadata: ['enabled' => $settings->enabled],
        );

        if (! $wasEnabled && $settings->enabled) {
            $this->dispatchReindex($tenantId, $eventId);
        }

        $settings->refresh();

        return response()->json([
            'data' => [
                'enabled' => $settings->enabled,
                'display_name' => [
                    'en' => $settings->display_name_en,
                    'ar' => $settings->display_name_ar,
                ],
                'greeting' => [
                    'en' => $settings->greeting_en,
                    'ar' => $settings->greeting_ar,
                ],
                'fallback_action' => $settings->fallback_action,
                'fallback_contact_email' => $settings->fallback_contact_email,
                'daily_question_limit' => $settings->daily_question_limit,
                'index' => [
                    'status' => $settings->index_status,
                    'version' => $settings->index_version,
                    'indexed_at' => $settings->indexed_at?->toIso8601String(),
                    'chunk_count' => $settings->chunk_count,
                    'error_code' => $settings->index_error_code,
                ],
                'provider' => [
                    'available' => app(LlmProvider::class)->isAvailable(),
                    'reason' => app(LlmProvider::class)->isAvailable() ? null : 'network_disabled',
                ],
            ],
        ]);
    }

    public function reindex(Request $request, AuditWriter $auditWriter): JsonResponse
    {
        $context = app(TenantContextStore::class)->current();
        $tenantId = (int) $context->tenant->id;
        $eventId = (int) $request->route('event_id');

        $this->dispatchReindex($tenantId, $eventId);

        $auditWriter->write(
            scope: 'tenant',
            tenantId: (string) $tenantId,
            action: 'event_assistant.reindexed',
            outcome: 'succeeded',
            actor: $context->actor,
            targetType: 'event',
            targetId: (string) $eventId,
        );

        $settings = EventAssistantSettings::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->first();

        return response()->json([
            'data' => [
                'index' => [
                    'status' => $settings?->index_status ?? 'pending',
                    'version' => $settings?->index_version ?? 0,
                    'indexed_at' => $settings?->indexed_at?->toIso8601String(),
                    'chunk_count' => $settings?->chunk_count ?? 0,
                    'error_code' => $settings?->index_error_code,
                ],
            ],
        ]);
    }

    public function usage(Request $request, GetAssistantUsage $query): JsonResponse
    {
        $context = app(TenantContextStore::class)->current();
        $tenantId = (int) $context->tenant->id;
        $eventId = (int) $request->route('event_id');

        $usage = $query->execute($tenantId, $eventId);

        return response()->json(['data' => $usage]);
    }

    public function deleteConversation(Request $request, AuditWriter $auditWriter): JsonResponse
    {
        $context = app(TenantContextStore::class)->current();
        $tenantId = (int) $context->tenant->id;
        $publicId = $request->route('public_id');

        $conversation = AssistantConversation::query()
            ->where('tenant_id', $tenantId)
            ->where('public_id', $publicId)
            ->first();

        if ($conversation === null) {
            return response()->json([
                'type' => 'https://docs.zonetec.example/problems/not_found',
                'title' => 'Not Found',
                'status' => 404,
            ], 404);
        }

        $eventId = $conversation->event_id;
        $conversation->turns()->delete();
        $conversation->delete();

        $auditWriter->write(
            scope: 'tenant',
            tenantId: (string) $tenantId,
            action: 'event_assistant.transcript_deleted',
            outcome: 'succeeded',
            actor: $context->actor,
            targetType: 'assistant_conversation',
            targetId: $publicId,
            metadata: ['event_id' => $eventId],
        );

        return response()->json(['data' => ['deleted' => true]]);
    }

    private function dispatchReindex(int $tenantId, int $eventId): void
    {
        $jobContext = TenantJobContext::capture(
            app(TenantContextStore::class),
            app(RequestContextStore::class),
        );

        RebuildEventKnowledgeIndexJob::dispatch($eventId, $jobContext);
    }
}
