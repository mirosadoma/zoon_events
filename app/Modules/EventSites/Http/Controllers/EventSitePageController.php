<?php

namespace App\Modules\EventSites\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\AdminConsole\Application\SessionContextBuilder;
use App\Modules\Ai\Application\Support\AiProviderStatus;
use App\Modules\Authorization\Application\PermissionEvaluator;
use App\Modules\Events\Application\Support\PublicRegistrationUrlBuilder;
use App\Modules\Events\Application\Support\ShareablePublicEventResolver;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\EventSites\Application\Queries\GetOrganizerSite;
use App\Modules\EventSites\Application\Support\PublicSiteUrl;
use App\Modules\EventSites\Application\Support\PublishedSitePresenter;
use App\Modules\EventSites\Infrastructure\Persistence\Models\EventSite;
use App\Modules\EventSites\Infrastructure\Persistence\Models\EventSiteVersion;
use App\Modules\Tenancy\Domain\Context\TenantContext;
use Inertia\Inertia;
use Inertia\Response;

final class EventSitePageController extends Controller
{
    public function __construct(
        private readonly SessionContextBuilder $sessions,
        private readonly PermissionEvaluator $permissions,
        private readonly GetOrganizerSite $getOrganizerSite,
        private readonly ShareablePublicEventResolver $shareableEvents,
        private readonly PublishedSitePresenter $presenter,
        private readonly PublicRegistrationUrlBuilder $registrationUrls,
    ) {}

    private function authorizeTenant(string $permission): TenantContext
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        $context = $this->sessions->tenantContextFor($user);
        abort_unless($context instanceof TenantContext, 403);
        abort_unless($this->permissions->hasTenantPermission($context, $permission), 403);

        return $context;
    }

    public function builder(string $locale, string $eventId): Response
    {
        $context = $this->authorizeTenant('event.manage');

        $event = Event::query()
            ->where('tenant_id', $context->tenant->id)
            ->with(['branding', 'agendaItems', 'venues'])
            ->findOrFail($eventId);

        $site = $this->getOrganizerSite->execute($context, $event);

        return Inertia::render('tenant/events/SiteBuilder', [
            'tenantId' => (string) $context->tenant->id,
            'event' => [
                'id' => (string) $event->id,
                'slug' => $event->slug,
                'name' => ['en' => $event->name_en, 'ar' => $event->name_ar],
                'status' => $event->status,
                'timezone' => $event->timezone,
            ],
            'site' => $site,
            'preview' => $this->presenter->builderPreview($event),
            'locale' => $locale,
        ]);
    }

    public function insights(string $locale, string $eventId): Response
    {
        $context = $this->authorizeTenant('reports.view');

        $event = Event::query()
            ->where('tenant_id', $context->tenant->id)
            ->findOrFail($eventId);

        $aiStatus = app(AiProviderStatus::class)->describe();

        return Inertia::render('tenant/events/AiInsights', [
            'tenantId' => (string) $context->tenant->id,
            'event' => [
                'id' => (string) $event->id,
                'name' => ['en' => $event->name_en, 'ar' => $event->name_ar],
                'status' => $event->status,
            ],
            'aiAvailable' => $aiStatus['available'],
            'aiStatus' => $aiStatus,
        ]);
    }

    public function publicShow(string $locale, string $eventSlug, ?string $pageSlug = null): Response
    {
        $event = PublicSiteUrl::findEventByPublicSlug($eventSlug);
        if ($event === null) {
            // Fallback to shareable resolver (tenant host + slug rules).
            $event = $this->shareableEvents->findBySlug($eventSlug);
        }

        $site = EventSite::query()
            ->where('tenant_id', $event->tenant_id)
            ->where('event_id', $event->id)
            ->where('status', 'published')
            ->first();

        if ($site === null || $site->live_version_id === null) {
            abort(404);
        }

        $version = EventSiteVersion::query()
            ->where('tenant_id', $event->tenant_id)
            ->where('event_id', $event->id)
            ->where('id', $site->live_version_id)
            ->first();

        if ($version === null) {
            abort(404);
        }

        $data = $this->presenter->present($event, $site, $version, $locale, $pageSlug);

        return Inertia::render('public/site/EventSite', [
            'locale' => $locale,
            'site' => $data,
        ]);
    }
}
