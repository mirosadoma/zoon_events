<?php

namespace App\Modules\Ai\Http\Controllers;

use App\Modules\Ai\Application\Actions\AnswerInsightQuestion;
use App\Modules\Ai\Application\Actions\GenerateEventInsight;
use App\Modules\Ai\Domain\AiProviderException;
use App\Modules\Ai\Http\Requests\AskInsightRequest;
use App\Modules\Ai\Http\Requests\GenerateInsightRequest;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Registration\Infrastructure\Persistence\Models\RegistrationSubmission;
use App\Modules\Scanning\Infrastructure\Persistence\Models\ScanEvent;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

final class AiInsightController extends Controller
{
    public function generate(
        GenerateInsightRequest $request,
        GenerateEventInsight $action,
    ): JsonResponse {
        $context = app(TenantContextStore::class)->current();
        $tenantId = (int) $context->tenant->id;
        $eventId = (int) $request->route('event_id');

        $metrics = $this->collectMetrics($tenantId, $eventId, $request->validated('metric_window'));

        try {
            $result = $action->execute(
                $tenantId,
                $eventId,
                $metrics,
                $request->validated('metric_window'),
                $request->validated('locale'),
                $context->actor,
                (bool) $request->validated('refresh', false),
            );
        } catch (AiProviderException $e) {
            return response()->json([
                'type' => 'https://docs.zonetec.example/problems/ai_unavailable',
                'title' => 'AI Provider Unavailable',
                'status' => 503,
                'code' => 'ai.provider_unavailable',
            ], 503);
        }

        if ($result['insufficient_data'] ?? false) {
            return response()->json([
                'data' => [
                    'outcome' => 'insufficient_data',
                    'metric_window' => $request->validated('metric_window'),
                    'metrics_used' => $result['metrics_used'],
                ],
            ]);
        }

        return response()->json([
            'data' => [
                'metric_window' => $request->validated('metric_window'),
                'generated_at' => $result['generated_at'],
                'expires_at' => $result['expires_at'],
                'ai_generated' => true,
                'provider_key' => 'fake',
                'cached' => $result['cached'],
                'summary' => $result['summary'],
                'highlights' => $result['highlights'],
                'metrics_used' => $result['metrics_used'],
            ],
        ]);
    }

    public function ask(
        AskInsightRequest $request,
        AnswerInsightQuestion $action,
    ): JsonResponse {
        $context = app(TenantContextStore::class)->current();
        $tenantId = (int) $context->tenant->id;
        $eventId = (int) $request->route('event_id');

        $metrics = $this->collectMetrics($tenantId, $eventId, $request->validated('metric_window'));

        try {
            $result = $action->execute(
                $tenantId,
                $eventId,
                $metrics,
                $request->validated('question'),
                $request->validated('locale'),
            );
        } catch (AiProviderException $e) {
            return response()->json([
                'type' => 'https://docs.zonetec.example/problems/ai_unavailable',
                'title' => 'AI Provider Unavailable',
                'status' => 503,
                'code' => 'ai.provider_unavailable',
            ], 503);
        }

        if ($result['outcome'] === 'out_of_scope') {
            return response()->json([
                'data' => [
                    'outcome' => 'out_of_scope',
                    'metrics_used' => $result['metrics_used'],
                ],
            ]);
        }

        if ($result['outcome'] === 'insufficient_data') {
            return response()->json([
                'data' => [
                    'outcome' => 'insufficient_data',
                    'metrics_used' => $result['metrics_used'],
                ],
            ]);
        }

        return response()->json([
            'data' => [
                'outcome' => 'answered',
                'answer' => $result['answer'],
                'metrics_used' => $result['metrics_used'],
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function collectMetrics(int $tenantId, int $eventId, string $metricWindow): array
    {
        $dateFilter = match ($metricWindow) {
            'last_7_days' => now()->subDays(7),
            'last_14_days' => now()->subDays(14),
            'last_30_days' => now()->subDays(30),
            default => null,
        };

        $registeredCount = RegistrationSubmission::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->when($dateFilter, fn ($q) => $q->where('created_at', '>=', $dateFilter))
            ->count();

        // scan_events stores decision in `result` (accepted / manual_override / …), not `outcome`.
        $checkedInCount = ScanEvent::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->whereIn('result', ['accepted', 'manual_override'])
            ->when($dateFilter, fn ($q) => $q->where('scanned_at', '>=', $dateFilter))
            ->distinct()
            ->count('credential_id');

        $event = Event::query()
            ->where('tenant_id', $tenantId)
            ->find($eventId);

        return [
            'registered_count' => $registeredCount,
            'checked_in_count' => $checkedInCount,
            'capacity' => $event?->capacity,
            'event_window' => [
                'start_at' => $event?->start_at?->toIso8601String(),
                'end_at' => $event?->end_at?->toIso8601String(),
            ],
            'funnel' => [
                'registered' => $registeredCount,
                'checked_in' => $checkedInCount,
            ],
        ];
    }
}
