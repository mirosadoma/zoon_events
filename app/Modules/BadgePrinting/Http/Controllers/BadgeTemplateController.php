<?php

namespace App\Modules\BadgePrinting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Authorization\Policies\Phase3\Phase3Policy;
use App\Modules\BadgePrinting\Application\Actions\ActivateBadgeTemplateAction;
use App\Modules\BadgePrinting\Application\Actions\CreateOrUpdateBadgeTemplateAction;
use App\Modules\BadgePrinting\Application\Actions\DeactivateBadgeTemplateAction;
use App\Modules\BadgePrinting\Application\Actions\PreviewBadgeTemplateWithTestDataAction;
use App\Modules\BadgePrinting\Application\Support\BadgeLayoutValidator;
use App\Modules\BadgePrinting\Application\Support\ResolveBadgeFormFieldKeys;
use App\Modules\BadgePrinting\Http\Requests\BadgeTemplateRequest;
use App\Modules\BadgePrinting\Http\Resources\BadgeTemplateResource;
use App\Modules\BadgePrinting\Infrastructure\Persistence\Models\BadgeTemplate;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class BadgeTemplateController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly TenantContextStore $contexts,
        private readonly Phase3Policy $policy,
        private readonly CreateOrUpdateBadgeTemplateAction $creatorUpdater,
        private readonly ActivateBadgeTemplateAction $activator,
        private readonly DeactivateBadgeTemplateAction $deactivator,
        private readonly PreviewBadgeTemplateWithTestDataAction $previewTestData,
        private readonly ResolveBadgeFormFieldKeys $formFieldKeys,
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
        $event = $this->eventOrFail($tenantId, $eventId);
        $extraFields = $this->formFieldKeys->keysForEvent($event);

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
            backgroundImagePath: $request->filled('background_image_path') ? $request->string('background_image_path')->toString() : null,
            clearBackgroundImage: $request->boolean('clear_background_image'),
            extraAllowedFields: $extraFields,
        );

        return $this->success((new BadgeTemplateResource($template))->resolve(), 201);
    }

    public function update(BadgeTemplateRequest $request, string $eventId, string $templateId): JsonResponse
    {
        $tenantId = $this->contexts->current()->tenant->id;
        $existing = $this->findOrFail($tenantId, $eventId, $templateId);
        $event = $this->eventOrFail($tenantId, $eventId);
        $extraFields = $this->formFieldKeys->keysForEvent($event);

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
            backgroundImagePath: $request->filled('background_image_path') ? $request->string('background_image_path')->toString() : null,
            clearBackgroundImage: $request->boolean('clear_background_image'),
            extraAllowedFields: $extraFields,
        );

        return $this->success((new BadgeTemplateResource($template))->resolve());
    }

    public function uploadBackground(Request $request, string $eventId, string $templateId): JsonResponse
    {
        $this->authorizeManage($request);

        $tenantId = $this->contexts->current()->tenant->id;
        $template = $this->findOrFail($tenantId, $eventId, $templateId);

        $validated = $request->validate([
            'background_image' => ['required', 'image', 'max:5120'],
        ]);

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $validated['background_image'];

        if ($template->background_image_path) {
            Storage::disk('public')->delete($template->background_image_path);
        }

        $path = $file->store("tenants/{$tenantId}/events/{$eventId}/badges/backgrounds", 'public');
        $template->forceFill(['background_image_path' => $path])->save();

        return $this->success((new BadgeTemplateResource($template->fresh()))->resolve());
    }

    public function previewTest(Request $request, string $eventId): JsonResponse
    {
        $this->authorizeManage($request);

        $tenantId = $this->contexts->current()->tenant->id;
        $event = $this->eventOrFail($tenantId, $eventId);
        $extraFields = $this->formFieldKeys->keysForEvent($event);

        $validated = $request->validate([
            'template_id' => ['nullable'],
            'name' => ['sometimes', 'string', 'max:120'],
            'layout' => ['required', 'array'],
            'paper_size' => ['required', 'string', 'max:40'],
            'printer_type' => ['sometimes', 'string', 'max:40'],
            'orientation' => ['nullable', 'string', 'in:portrait,landscape'],
            'background_color' => ['nullable', 'string', 'max:7'],
            'background_gradient' => ['nullable', 'array'],
            'background_image_path' => ['nullable', 'string', 'max:500'],
            'canvas_width' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'canvas_height' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'field_values' => ['sometimes', 'array'],
            'qr_payload' => ['nullable', 'string', 'max:500'],
        ]);

        $layout = (array) ($validated['layout'] ?? []);
        app(BadgeLayoutValidator::class)->validate($layout, $extraFields);

        $template = null;
        $templateId = $validated['template_id'] ?? null;
        if ($templateId !== null && $templateId !== '') {
            $template = $this->findOrFail($tenantId, $eventId, (string) $templateId);
        }

        $draft = $template ? $template->replicate() : new BadgeTemplate;
        $draft->forceFill([
            'tenant_id' => $tenantId,
            'event_id' => $eventId,
            'name' => $validated['name'] ?? ($template?->name ?? 'Preview'),
            'layout' => $layout,
            'paper_size' => $validated['paper_size'],
            'printer_type' => $validated['printer_type'] ?? ($template?->printer_type ?? 'thermal'),
            'orientation' => $validated['orientation'] ?? ($template?->orientation ?? 'portrait'),
            'background_color' => $validated['background_color'] ?? $template?->background_color,
            'background_gradient' => $validated['background_gradient'] ?? $template?->background_gradient,
            'background_image_path' => $validated['background_image_path'] ?? $template?->background_image_path,
            'canvas_width' => $validated['canvas_width'] ?? $template?->canvas_width,
            'canvas_height' => $validated['canvas_height'] ?? $template?->canvas_height,
            'status' => 'draft',
        ]);

        $preview = $this->previewTestData->execute(
            $draft,
            is_array($validated['field_values'] ?? null) ? $validated['field_values'] : [],
            isset($validated['qr_payload']) ? (string) $validated['qr_payload'] : null,
        );

        if ($preview === null) {
            throw ValidationException::withMessages([
                'preview' => 'Unable to render badge preview. Ensure the GD extension is enabled.',
            ]);
        }

        return $this->success($preview);
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

    private function eventOrFail(string $tenantId, string $eventId): Event
    {
        return Event::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($eventId)
            ->firstOrFail();
    }

    private function authorizeManage(Request $request): void
    {
        $user = $request->user();

        if ($user === null || ! $this->policy->allows($user, 'manageBadgeTemplate')) {
            abort(403);
        }
    }
}
