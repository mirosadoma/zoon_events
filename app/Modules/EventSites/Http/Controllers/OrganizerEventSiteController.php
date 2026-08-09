<?php

namespace App\Modules\EventSites\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\EventSites\Application\Actions\PublishSite;
use App\Modules\EventSites\Application\Actions\RestoreSiteVersion;
use App\Modules\EventSites\Application\Actions\SaveSiteDraft;
use App\Modules\EventSites\Application\Actions\UnpublishSite;
use App\Modules\EventSites\Application\Queries\GetOrganizerSite;
use App\Modules\EventSites\Http\Requests\PublishSiteRequest;
use App\Modules\EventSites\Http\Requests\SaveSiteDraftRequest;
use App\Modules\EventSites\Http\Requests\SiteMediaUploadRequest;
use App\Modules\EventSites\Infrastructure\Persistence\Models\EventSite;
use App\Modules\EventSites\Infrastructure\Persistence\Models\EventSiteFormSubmission;
use App\Modules\EventSites\Infrastructure\Persistence\Models\EventSiteVersion;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

final class OrganizerEventSiteController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly TenantContextStore $contexts,
    ) {}

    public function show(string $eventId, GetOrganizerSite $query): JsonResponse
    {
        $context = $this->contexts->current();
        $event = $this->findEvent($context->tenant->id, $eventId);
        $data = $query->execute($context, $event);

        return $this->success($data);
    }

    public function saveDraft(string $eventId, SaveSiteDraftRequest $request, SaveSiteDraft $action): JsonResponse
    {
        $context = $this->contexts->current();
        $event = $this->findEvent($context->tenant->id, $eventId);

        $data = $action->execute(
            $context,
            $event,
            (int) $request->validated('draft_revision'),
            (array) $request->validated('blocks'),
            (array) $request->validated('settings', []),
        );

        return $this->success($data);
    }

    public function publish(string $eventId, PublishSiteRequest $request, PublishSite $action): JsonResponse
    {
        $context = $this->contexts->current();
        $event = $this->findEvent($context->tenant->id, $eventId);
        $data = $action->execute($context, $event);

        return $this->success($data, $data['already_published'] ? 200 : 201);
    }

    public function unpublish(string $eventId, UnpublishSite $action): JsonResponse
    {
        $context = $this->contexts->current();
        $event = $this->findEvent($context->tenant->id, $eventId);
        $data = $action->execute($context, $event);

        return $this->success($data);
    }

    public function versions(string $eventId): JsonResponse
    {
        $context = $this->contexts->current();
        $event = $this->findEvent($context->tenant->id, $eventId);

        $site = EventSite::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $event->id)
            ->first();

        if ($site === null) {
            return $this->success(['versions' => []]);
        }

        $versions = EventSiteVersion::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('event_site_id', $site->id)
            ->orderByDesc('version')
            ->limit(50)
            ->get();

        return $this->success([
            'versions' => $versions->map(fn (EventSiteVersion $v): array => [
                'id' => $v->id,
                'version' => $v->version,
                'status' => $v->status,
                'published_at' => $v->published_at?->toIso8601String(),
                'published_by' => $v->published_by_user_id,
                'block_count' => $v->block_count,
            ])->all(),
        ]);
    }

    public function restore(string $eventId, string $versionId, RestoreSiteVersion $action): JsonResponse
    {
        $context = $this->contexts->current();
        $event = $this->findEvent($context->tenant->id, $eventId);
        $data = $action->execute($context, $event, (int) $versionId);

        return $this->success($data);
    }

    public function media(string $eventId, SiteMediaUploadRequest $request): JsonResponse
    {
        $context = $this->contexts->current();
        $event = $this->findEvent($context->tenant->id, $eventId);

        $file = $request->file('file');
        $extension = $file->getClientOriginalExtension();
        $filename = Str::random(32).'.'.$extension;
        $directory = "event-sites/{$context->tenant->id}/{$event->id}";
        $path = $file->storeAs($directory, $filename, 'public');

        return $this->success([
            'path' => $path,
            'url' => '/storage/'.$path,
        ]);
    }

    public function submissions(string $eventId): JsonResponse
    {
        $context = $this->contexts->current();
        $event = $this->findEvent($context->tenant->id, $eventId);

        $rows = EventSiteFormSubmission::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $event->id)
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        return $this->success([
            'submissions' => $rows->map(static fn ($row): array => [
                'id' => $row->id,
                'page_id' => $row->page_id,
                'page_title' => $row->page_title,
                'block_id' => $row->block_id,
                'form_name' => $row->form_name,
                'payload' => $row->payload,
                'locale' => $row->locale,
                'created_at' => $row->created_at?->toIso8601String(),
            ])->all(),
        ]);
    }

    private function findEvent(int $tenantId, string $eventId): Event
    {
        return Event::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($eventId);
    }
}
