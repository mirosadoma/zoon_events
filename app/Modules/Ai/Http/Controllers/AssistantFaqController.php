<?php

namespace App\Modules\Ai\Http\Controllers;

use App\Modules\Ai\Application\Jobs\RebuildEventKnowledgeIndexJob;
use App\Modules\Ai\Http\Requests\StoreAssistantFaqRequest;
use App\Modules\Ai\Http\Requests\UpdateAssistantFaqRequest;
use App\Modules\Ai\Infrastructure\Persistence\Models\EventAssistantFaq;
use App\Modules\Ai\Infrastructure\Persistence\Models\EventAssistantSettings;
use App\Modules\Audit\Contracts\AuditWriter;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Shared\Domain\Context\RequestContextStore;
use App\Modules\Tenancy\Contracts\Queue\TenantJobContext;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class AssistantFaqController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        [$tenantId, $eventId] = $this->scopedEventIds($request);

        $faqs = EventAssistantFaq::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (EventAssistantFaq $faq) => $this->present($faq))
            ->values();

        return response()->json(['data' => ['faqs' => $faqs]]);
    }

    public function store(
        StoreAssistantFaqRequest $request,
        AuditWriter $auditWriter,
    ): JsonResponse {
        [$tenantId, $eventId] = $this->scopedEventIds($request);
        $data = $request->validated();

        $maxSort = (int) EventAssistantFaq::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->max('sort_order');

        $faq = EventAssistantFaq::query()->create([
            'tenant_id' => $tenantId,
            'event_id' => $eventId,
            'question_en' => $data['question_en'],
            'question_ar' => $data['question_ar'],
            'answer_en' => $data['answer_en'],
            'answer_ar' => $data['answer_ar'],
            'sort_order' => $data['sort_order'] ?? ($maxSort + 1),
            'is_active' => $data['is_active'] ?? true,
        ]);

        $this->ensureSettings($tenantId, $eventId);
        $this->dispatchReindex($tenantId, $eventId);

        $auditWriter->write(
            scope: 'tenant',
            tenantId: (string) $tenantId,
            action: 'event_assistant.faq_created',
            outcome: 'succeeded',
            actor: app(TenantContextStore::class)->current()->actor,
            targetType: 'event_assistant_faq',
            targetId: (string) $faq->id,
            metadata: ['event_id' => $eventId],
        );

        return response()->json(['data' => $this->present($faq)], 201);
    }

    public function update(
        UpdateAssistantFaqRequest $request,
        AuditWriter $auditWriter,
    ): JsonResponse {
        [$tenantId, $eventId] = $this->scopedEventIds($request);
        $faq = $this->findScopedFaq($tenantId, $eventId, (int) $request->route('faq_id'));

        if ($faq === null) {
            return $this->notFound();
        }

        $faq->fill($request->validated());
        $faq->save();

        $this->dispatchReindex($tenantId, $eventId);

        $auditWriter->write(
            scope: 'tenant',
            tenantId: (string) $tenantId,
            action: 'event_assistant.faq_updated',
            outcome: 'succeeded',
            actor: app(TenantContextStore::class)->current()->actor,
            targetType: 'event_assistant_faq',
            targetId: (string) $faq->id,
            metadata: ['event_id' => $eventId],
        );

        return response()->json(['data' => $this->present($faq->fresh())]);
    }

    public function destroy(Request $request, AuditWriter $auditWriter): JsonResponse
    {
        [$tenantId, $eventId] = $this->scopedEventIds($request);
        $faq = $this->findScopedFaq($tenantId, $eventId, (int) $request->route('faq_id'));

        if ($faq === null) {
            return $this->notFound();
        }

        $faqId = $faq->id;
        $faq->delete();
        $this->dispatchReindex($tenantId, $eventId);

        $auditWriter->write(
            scope: 'tenant',
            tenantId: (string) $tenantId,
            action: 'event_assistant.faq_deleted',
            outcome: 'succeeded',
            actor: app(TenantContextStore::class)->current()->actor,
            targetType: 'event_assistant_faq',
            targetId: (string) $faqId,
            metadata: ['event_id' => $eventId],
        );

        return response()->json(['data' => ['deleted' => true]]);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function scopedEventIds(Request $request): array
    {
        $context = app(TenantContextStore::class)->current();
        $tenantId = (int) $context->tenant->id;
        $eventId = (int) $request->route('event_id');

        Event::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $eventId)
            ->firstOrFail();

        return [$tenantId, $eventId];
    }

    private function findScopedFaq(int $tenantId, int $eventId, int $faqId): ?EventAssistantFaq
    {
        return EventAssistantFaq::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('id', $faqId)
            ->first();
    }

    private function ensureSettings(int $tenantId, int $eventId): void
    {
        EventAssistantSettings::query()->firstOrCreate(
            ['tenant_id' => $tenantId, 'event_id' => $eventId],
            ['daily_question_limit' => (int) config('ai.assistant.event_questions_per_day', 500)],
        );
    }

    private function dispatchReindex(int $tenantId, int $eventId): void
    {
        $jobContext = TenantJobContext::capture(
            app(TenantContextStore::class),
            app(RequestContextStore::class),
        );

        RebuildEventKnowledgeIndexJob::dispatch($eventId, $jobContext);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(EventAssistantFaq $faq): array
    {
        return [
            'id' => (string) $faq->id,
            'question_en' => $faq->question_en,
            'question_ar' => $faq->question_ar,
            'answer_en' => $faq->answer_en,
            'answer_ar' => $faq->answer_ar,
            'sort_order' => $faq->sort_order,
            'is_active' => $faq->is_active,
        ];
    }

    private function notFound(): JsonResponse
    {
        return response()->json([
            'type' => 'https://docs.zonetec.example/problems/not_found',
            'title' => 'Not Found',
            'status' => 404,
            'code' => 'not_found',
        ], 404);
    }
}
