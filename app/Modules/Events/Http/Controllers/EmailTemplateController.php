<?php

namespace App\Modules\Events\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AdminConsole\Application\SessionContextBuilder;
use App\Modules\AdminConsole\Http\Controllers\Concerns\ResolvesRouteParam;
use App\Modules\Authorization\Application\PermissionEvaluator;
use App\Modules\Events\Domain\EventEmailTemplateTypes;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Events\Infrastructure\Persistence\Models\EventEmailTemplate;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class EmailTemplateController extends Controller
{
    use ResolvesRouteParam;
    use RespondsWithApi;

    public function __construct(
        private readonly TenantContextStore $contextStore,
        private readonly SessionContextBuilder $sessions,
        private readonly PermissionEvaluator $permissions,
    ) {}

    public function index(): Response
    {
        [$context, $event] = $this->authorizeEventPage($this->routeParam('event_id'));

        $templates = EventEmailTemplate::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $event->id)
            ->orderBy('type')
            ->get()
            ->map(fn (EventEmailTemplate $template) => $this->mapTemplate($template))
            ->values()
            ->all();

        return Inertia::render('tenant/events/EmailTemplates', [
            'event' => [
                'id' => (string) $event->id,
                'name' => ['en' => $event->name_en, 'ar' => $event->name_ar],
                'status' => (string) $event->status,
            ],
            'templates' => $templates,
            'requiredTypes' => EventEmailTemplateTypes::REQUIRED,
            'configuredCount' => EventEmailTemplateTypes::configuredCount((string) $context->tenant->id, $event->id),
            'requiredCount' => EventEmailTemplateTypes::requiredCount(),
            'tenantId' => (string) $context->tenant->id,
        ]);
    }

    public function show()
    {
        $event = $this->apiEvent($this->routeParam('event_id'));
        $type = $this->routeParam('type');

        $template = EventEmailTemplate::query()
            ->where('tenant_id', $event->tenant_id)
            ->where('event_id', $event->id)
            ->where('type', $type)
            ->first();

        if (! $template) {
            return $this->success(null);
        }

        return $this->success($this->mapTemplate($template));
    }

    public function edit(): Response
    {
        [$context, $event] = $this->authorizeEventPage($this->routeParam('event_id'));
        $type = $this->routeParam('type');
        abort_unless(in_array($type, EventEmailTemplateTypes::REQUIRED, true), 404);

        $template = EventEmailTemplate::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $event->id)
            ->where('type', $type)
            ->first();

        return Inertia::render('tenant/events/EmailTemplateEditor', [
            'event' => [
                'id' => (string) $event->id,
                'name' => ['en' => $event->name_en, 'ar' => $event->name_ar],
            ],
            'type' => $type,
            'template' => $template ? $this->mapTemplate($template) : null,
            'tenantId' => (string) $context->tenant->id,
        ]);
    }

    public function update(Request $request)
    {
        $event = $this->apiEvent($this->routeParam('event_id'));
        $type = $this->routeParam('type');
        abort_unless(in_array($type, EventEmailTemplateTypes::REQUIRED, true), 404);

        $validated = $request->validate([
            'subject_en' => ['required', 'string', 'max:255'],
            'subject_ar' => ['required', 'string', 'max:255'],
            'html_body_en' => ['required', 'string'],
            'html_body_ar' => ['required', 'string'],
        ]);

        $template = EventEmailTemplate::query()
            ->where('tenant_id', $event->tenant_id)
            ->where('event_id', $event->id)
            ->where('type', $type)
            ->first();

        if ($template) {
            $template->update($validated);
        } else {
            $template = EventEmailTemplate::create([
                'event_id' => $event->id,
                'tenant_id' => $event->tenant_id,
                'type' => $type,
                ...$validated,
            ]);
        }

        return $this->success($this->mapTemplate($template->fresh() ?? $template));
    }

    public function destroy()
    {
        $event = $this->apiEvent($this->routeParam('event_id'));
        $type = $this->routeParam('type');
        abort_unless(in_array($type, EventEmailTemplateTypes::REQUIRED, true), 404);

        EventEmailTemplate::query()
            ->where('tenant_id', $event->tenant_id)
            ->where('event_id', $event->id)
            ->where('type', $type)
            ->delete();

        return $this->empty();
    }

    public function uploadImage(Request $request)
    {
        $event = $this->apiEvent($this->routeParam('event_id'));

        $validated = $request->validate([
            'image' => ['required', 'image', 'max:5120'],
        ]);

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $validated['image'];

        $path = $file->store(
            "tenants/{$event->tenant_id}/events/{$event->id}/email-templates",
            'public',
        );

        $relativeUrl = Storage::disk('public')->url($path);
        $absoluteUrl = str_starts_with($relativeUrl, 'http://') || str_starts_with($relativeUrl, 'https://')
            ? $relativeUrl
            : url($relativeUrl);

        return $this->success([
            'path' => $path,
            'url' => $absoluteUrl,
        ]);
    }

    /** @return array{0:\App\Modules\Tenancy\Domain\Context\TenantContext,1:Event} */
    private function authorizeEventPage(string $eventId): array
    {
        $user = request()->user();
        abort_unless($user !== null, 403);

        $context = $this->sessions->tenantContextFor($user);
        abort_unless($context !== null, 403);
        abort_unless($this->permissions->hasTenantPermission($context, 'event.manage'), 403);

        $event = Event::query()
            ->where('tenant_id', $context->tenant->id)
            ->findOrFail($eventId);

        return [$context, $event];
    }

    private function apiEvent(string $eventId): Event
    {
        $context = $this->contextStore->current();

        return Event::query()
            ->where('tenant_id', $context->tenant->id)
            ->findOrFail($eventId);
    }

    /** @return array<string, mixed> */
    private function mapTemplate(EventEmailTemplate $template): array
    {
        return [
            'id' => (string) $template->id,
            'event_id' => (string) $template->event_id,
            'type' => $template->type,
            'subject_en' => $template->subject_en,
            'subject_ar' => $template->subject_ar,
            'html_body_en' => $template->html_body_en,
            'html_body_ar' => $template->html_body_ar,
        ];
    }
}
