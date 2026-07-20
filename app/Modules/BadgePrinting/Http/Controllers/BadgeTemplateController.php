<?php

namespace App\Modules\BadgePrinting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Authorization\Policies\Phase3\Phase3Policy;
use App\Modules\BadgePrinting\Application\Actions\ActivateBadgeTemplateAction;
use App\Modules\BadgePrinting\Application\Actions\CreateOrUpdateBadgeTemplateAction;
use App\Modules\BadgePrinting\Application\Actions\DeactivateBadgeTemplateAction;
use App\Modules\BadgePrinting\Http\Requests\BadgeTemplateRequest;
use App\Modules\BadgePrinting\Http\Resources\BadgeTemplateResource;
use App\Modules\BadgePrinting\Infrastructure\Persistence\Models\BadgeTemplate;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class BadgeTemplateController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly TenantContextStore $contexts,
        private readonly Phase3Policy $policy,
        private readonly CreateOrUpdateBadgeTemplateAction $creatorUpdater,
        private readonly ActivateBadgeTemplateAction $activator,
        private readonly DeactivateBadgeTemplateAction $deactivator,
    ) {}

    public function index(Request $request, string $eventId): AnonymousResourceCollection
    {
        $this->authorizeManage($request);

        $tenantId = $this->contexts->current()->tenant->id;

        $templates = BadgeTemplate::where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->orderBy('created_at', 'desc')
            ->get();

        return BadgeTemplateResource::collection($templates);
    }

    public function store(BadgeTemplateRequest $request, string $eventId): JsonResponse
    {
        $tenantId = $this->contexts->current()->tenant->id;

        $template = $this->creatorUpdater->execute(
            tenantId: $tenantId,
            eventId: $eventId,
            existing: null,
            name: $request->string('name')->toString(),
            layout: (array) $request->input('layout'),
            paperSize: $request->string('paper_size')->toString(),
            printerType: $request->string('printer_type')->toString(),
            orientation: $request->filled('orientation') ? $request->string('orientation')->toString() : null,
            backgroundColor: $request->filled('background_color') ? $request->string('background_color')->toString() : null,
            backgroundGradient: $request->filled('background_gradient') ? (array) $request->input('background_gradient') : null,
            canvasWidth: $request->filled('canvas_width') ? $request->integer('canvas_width') : null,
            canvasHeight: $request->filled('canvas_height') ? $request->integer('canvas_height') : null,
        );

        return $this->success((new BadgeTemplateResource($template))->resolve(), 201);
    }

    public function update(BadgeTemplateRequest $request, string $eventId, string $templateId): JsonResponse
    {
        $tenantId = $this->contexts->current()->tenant->id;
        $existing = $this->findOrFail($tenantId, $eventId, $templateId);

        $template = $this->creatorUpdater->execute(
            tenantId: $tenantId,
            eventId: $eventId,
            existing: $existing,
            name: $request->string('name')->toString(),
            layout: (array) $request->input('layout'),
            paperSize: $request->string('paper_size')->toString(),
            printerType: $request->string('printer_type')->toString(),
            orientation: $request->filled('orientation') ? $request->string('orientation')->toString() : null,
            backgroundColor: $request->filled('background_color') ? $request->string('background_color')->toString() : null,
            backgroundGradient: $request->filled('background_gradient') ? (array) $request->input('background_gradient') : null,
            canvasWidth: $request->filled('canvas_width') ? $request->integer('canvas_width') : null,
            canvasHeight: $request->filled('canvas_height') ? $request->integer('canvas_height') : null,
        );

        return $this->success((new BadgeTemplateResource($template))->resolve());
    }

    public function activate(Request $request, string $eventId, string $templateId): JsonResponse
    {
        $this->authorizeManage($request);

        $tenantId = $this->contexts->current()->tenant->id;
        $template = $this->findOrFail($tenantId, $eventId, $templateId);

        $this->activator->execute($template);

        return $this->success((new BadgeTemplateResource($template->fresh()))->resolve());
    }

    public function deactivate(Request $request, string $eventId, string $templateId): JsonResponse
    {
        $this->authorizeManage($request);

        $tenantId = $this->contexts->current()->tenant->id;
        $template = $this->findOrFail($tenantId, $eventId, $templateId);

        $this->deactivator->execute($template);

        return $this->success((new BadgeTemplateResource($template->fresh()))->resolve());
    }

    private function findOrFail(string $tenantId, string $eventId, string $templateId): BadgeTemplate
    {
        return BadgeTemplate::where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->findOrFail($templateId);
    }

    private function authorizeManage(Request $request): void
    {
        $user = $request->user();

        if ($user === null || ! $this->policy->allows($user, 'manageBadgeTemplate')) {
            abort(403);
        }
    }
}
