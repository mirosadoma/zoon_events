<?php

namespace App\Modules\EventSites\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Modules\Events\Domain\PublicEventContextStore;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\EventSites\Application\Actions\SubmitSiteForm;
use App\Modules\EventSites\Application\Support\PublishedSitePresenter;
use App\Modules\EventSites\Infrastructure\Persistence\Models\EventSite;
use App\Modules\EventSites\Infrastructure\Persistence\Models\EventSiteVersion;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PublicEventSiteController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly PublicEventContextStore $contexts,
        private readonly PublishedSitePresenter $presenter,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $context = $this->contexts->current();

        $event = Event::query()
            ->where('tenant_id', $context->tenantId)
            ->where('id', $context->eventId)
            ->first();

        if ($event === null) {
            abort(404);
        }

        $site = EventSite::query()
            ->where('tenant_id', $context->tenantId)
            ->where('event_id', $context->eventId)
            ->where('status', 'published')
            ->first();

        if ($site === null || $site->live_version_id === null) {
            abort(404);
        }

        $version = EventSiteVersion::query()
            ->where('tenant_id', $context->tenantId)
            ->where('event_id', $context->eventId)
            ->where('id', $site->live_version_id)
            ->first();

        if ($version === null) {
            abort(404);
        }

        $locale = request()->header('Accept-Language', 'en');
        $locale = str_starts_with((string) $locale, 'ar') ? 'ar' : 'en';
        $pageSlug = $request->query('page');
        $pageSlug = is_string($pageSlug) && $pageSlug !== '' ? $pageSlug : null;

        $data = $this->presenter->present($event, $site, $version, $locale, $pageSlug);

        return $this->success($data);
    }

    public function submitForm(Request $request, string $eventSlug, string $blockId, SubmitSiteForm $action): JsonResponse
    {
        $context = $this->contexts->current();
        $locale = in_array($request->input('locale'), ['en', 'ar'], true)
            ? (string) $request->input('locale')
            : 'en';
        $pageId = (string) $request->input('page_id', 'home');
        $payload = is_array($request->input('fields')) ? $request->input('fields') : [];
        $visitorHash = hash('sha256', implode('|', [
            (string) $request->ip(),
            (string) $request->userAgent(),
            config('app.key'),
        ]));

        $result = $action->execute(
            (int) $context->tenantId,
            (int) $context->eventId,
            $blockId,
            $pageId,
            $locale,
            $payload,
            $visitorHash,
        );

        return $this->success($result, 201);
    }
}
