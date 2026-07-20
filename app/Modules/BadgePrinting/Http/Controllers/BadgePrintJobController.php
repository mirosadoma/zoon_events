<?php

namespace App\Modules\BadgePrinting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Authorization\Policies\Phase3\Phase3Policy;
use App\Modules\BadgePrinting\Application\Actions\BuildBadgePrintDocumentAction;
use App\Modules\BadgePrinting\Application\Actions\CreateBadgePrintJobAction;
use App\Modules\BadgePrinting\Application\Actions\ReprintBadgeAction;
use App\Modules\BadgePrinting\Http\Requests\CreateBadgePrintJobRequest;
use App\Modules\BadgePrinting\Http\Requests\PreviewBadgePrintJobRequest;
use App\Modules\BadgePrinting\Http\Requests\ReprintBadgeRequest;
use App\Modules\BadgePrinting\Http\Resources\BadgePrintJobResource;
use App\Modules\BadgePrinting\Infrastructure\Persistence\Models\BadgePrintJob;
use App\Modules\BadgePrinting\Infrastructure\Persistence\Models\BadgeTemplate;
use App\Modules\Shared\Http\Problems\Phase3Problem;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class BadgePrintJobController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly TenantContextStore $contexts,
        private readonly Phase3Policy $policy,
        private readonly BuildBadgePrintDocumentAction $printDocuments,
    ) {}

    public function index(Request $request, string $eventId): JsonResponse
    {
        $user = $request->user();

        if ($user === null || (! $this->policy->allows($user, 'printBadge') && ! $this->policy->allows($user, 'reprintBadge'))) {
            abort(403);
        }

        $context = $this->contexts->current();

        $query = BadgePrintJob::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $eventId)
            ->latest('created_at')
            ->limit(200);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        $jobs = $query->get()
            ->map(fn (BadgePrintJob $job): array => (new BadgePrintJobResource($job))->resolve())
            ->values()
            ->all();

        return $this->success($jobs);
    }

    public function store(
        CreateBadgePrintJobRequest $request,
        string $eventId,
        CreateBadgePrintJobAction $action,
    ): JsonResponse {
        $user = $request->user();

        if ($user === null || ! $this->policy->allows($user, 'printBadge')) {
            abort(403);
        }

        $context = $this->contexts->current();
        $attendeeId = $request->string('attendee_id')->toString();
        $credentialId = $request->string('credential_id')->toString();
        $fieldOverrides = $request->fieldOverrides();

        $job = $action->execute(
            tenantId: $context->tenant->id,
            eventId: $eventId,
            attendeeId: $attendeeId,
            credentialId: $credentialId,
            kioskId: null,
            printedByUserId: (string) $user->id,
        );

        $payload = (new BadgePrintJobResource($job))->resolve();
        $document = $this->buildDocument(
            (string) $context->tenant->id,
            $eventId,
            $attendeeId,
            $credentialId,
            $job->badge_template_id !== null ? (string) $job->badge_template_id : null,
            $fieldOverrides,
            true,
        );
        $payload['print_html'] = $document['html'] ?? null;
        $payload['fields'] = $document['fields'] ?? [];

        return $this->success($payload, 201);
    }

    public function preview(
        PreviewBadgePrintJobRequest $request,
        string $eventId,
    ): JsonResponse {
        $user = $request->user();

        if ($user === null || (! $this->policy->allows($user, 'printBadge') && ! $this->policy->allows($user, 'reprintBadge'))) {
            abort(403);
        }

        $context = $this->contexts->current();
        $document = $this->buildDocument(
            (string) $context->tenant->id,
            $eventId,
            $request->string('attendee_id')->toString(),
            $request->string('credential_id')->toString(),
            null,
            $request->fieldOverrides(),
            false,
        );

        if ($document === null) {
            throw Phase3Problem::make('badge_template_not_active');
        }

        return $this->success([
            'print_html' => $document['html'],
            'fields' => $document['fields'],
            'editable_fields' => $document['editable_fields'],
        ]);
    }

    public function reprint(
        ReprintBadgeRequest $request,
        string $eventId,
        string $badgePrintJobId,
        ReprintBadgeAction $action,
    ): JsonResponse {
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        $context = $this->contexts->current();

        $targetJob = BadgePrintJob::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $eventId)
            ->findOrFail($badgePrintJobId);

        $job = $action->execute(
            actor: $user,
            tenantContext: $context,
            eventId: $eventId,
            badgePrintJobId: (string) $targetJob->id,
            reason: $request->string('reprint_reason')->toString(),
        );

        $payload = (new BadgePrintJobResource($job))->resolve();
        $document = $this->buildDocument(
            (string) $context->tenant->id,
            $eventId,
            (string) $job->attendee_id,
            (string) $job->credential_id,
            $job->badge_template_id !== null ? (string) $job->badge_template_id : null,
            $request->fieldOverrides(),
            true,
        );
        $payload['print_html'] = $document['html'] ?? null;
        $payload['fields'] = $document['fields'] ?? [];

        return $this->success($payload, 200);
    }

    /**
     * @param  array<string, string|null>  $fieldOverrides
     * @return array{html: string, fields: array<string, string|null>, editable_fields: list<string>}|null
     */
    private function buildDocument(
        string $tenantId,
        string $eventId,
        string $attendeeId,
        string $credentialId,
        ?string $templateId,
        array $fieldOverrides = [],
        bool $autoPrint = true,
    ): ?array {
        $template = $templateId !== null
            ? BadgeTemplate::query()
                ->where('tenant_id', $tenantId)
                ->where('event_id', $eventId)
                ->find($templateId)
            : null;

        if ($template === null) {
            $template = BadgeTemplate::query()
                ->where('tenant_id', $tenantId)
                ->where('event_id', $eventId)
                ->where('status', 'active')
                ->orderByDesc('id')
                ->first();
        }

        if ($template === null) {
            return null;
        }

        return $this->printDocuments->build(
            $tenantId,
            $eventId,
            $attendeeId,
            $credentialId,
            $template,
            $fieldOverrides,
            $autoPrint,
        );
    }
}
