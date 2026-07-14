<?php

namespace App\Modules\AdminConsole\Http\Controllers\Tenant\Acs;

use App\Http\Controllers\Controller;
use App\Modules\AccessControl\Application\Queries\GateEventsQuery;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsAuthorizationRule;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsLane;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsZone;
use App\Modules\AdminConsole\Application\SessionContextBuilder;
use App\Modules\AdminConsole\Http\Controllers\Tenant\CheckIn\Concerns\AuthorizesTenantEventPage;
use App\Modules\AdminConsole\ViewModels\Acs\AcsPageViewModel;
use App\Modules\Authorization\Application\PermissionEvaluator;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Tenancy\Domain\Context\TenantContext;
use App\Modules\Ticketing\Infrastructure\Persistence\Models\TicketType;
use Inertia\Inertia;
use Inertia\Response;

final class AcsPageController extends Controller
{
    use AuthorizesTenantEventPage;

    public function __construct(
        private readonly SessionContextBuilder $sessions,
        private readonly PermissionEvaluator $permissions,
        private readonly AcsPageViewModel $viewModel,
        private readonly GateEventsQuery $gateEventsQuery,
    ) {}

    public function overview(string $eventId): Response
    {
        [$context, $event] = $this->authorizeAcsOverview($eventId);

        return Inertia::render(
            'tenant/acs/Index',
            $this->viewModel->overview($event, $context->tenant->id),
        );
    }

    public function zones(string $eventId): Response
    {
        [$context, $event] = $this->authorizeTenantEvent(
            $this->sessions,
            $this->permissions,
            $eventId,
            'acs.configure',
        );

        $zones = AcsZone::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $event->id)
            ->orderBy('name')
            ->get();

        return Inertia::render(
            'tenant/acs/Zones',
            $this->viewModel->zones($event, $context->tenant->id, $zones),
        );
    }

    public function lanes(string $eventId): Response
    {
        [$context, $event] = $this->authorizeTenantEvent(
            $this->sessions,
            $this->permissions,
            $eventId,
            'acs.configure',
        );

        $zones = AcsZone::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $event->id)
            ->orderBy('name')
            ->get();

        $lanes = AcsLane::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $event->id)
            ->orderBy('name')
            ->get();

        return Inertia::render(
            'tenant/acs/Lanes',
            $this->viewModel->lanes($event, $context->tenant->id, $zones, $lanes),
        );
    }

    public function rules(string $eventId): Response
    {
        [$context, $event] = $this->authorizeTenantEvent(
            $this->sessions,
            $this->permissions,
            $eventId,
            'acs.configure',
        );

        $zones = AcsZone::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $event->id)
            ->orderBy('name')
            ->get();

        $lanes = AcsLane::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $event->id)
            ->orderBy('name')
            ->get();

        $rules = AcsAuthorizationRule::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $event->id)
            ->orderByDesc('created_at')
            ->get();

        $ticketTypes = TicketType::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $event->id)
            ->orderBy('name_en')
            ->get();

        return Inertia::render(
            'tenant/acs/Rules',
            $this->viewModel->rules($event, $context->tenant->id, $zones, $lanes, $rules, $ticketTypes),
        );
    }

    public function accessLogs(string $eventId): Response
    {
        [$context, $event] = $this->authorizeTenantEvent(
            $this->sessions,
            $this->permissions,
            $eventId,
            'acs.events.view',
        );

        $events = $this->gateEventsQuery->list($context->tenant->id, $event->id, null, 50);

        return Inertia::render(
            'tenant/acs/AccessLogs',
            $this->viewModel->accessLogs($event, $context->tenant->id, $events),
        );
    }

    public function gateHealth(string $eventId): Response
    {
        [$context, $event] = $this->authorizeTenantEvent(
            $this->sessions,
            $this->permissions,
            $eventId,
            'acs.health.view',
        );

        return Inertia::render(
            'tenant/acs/GateHealth',
            $this->viewModel->gateHealth($event, $context->tenant->id),
        );
    }

    /** @return array{0: TenantContext, 1: Event} */
    private function authorizeAcsOverview(string $eventId): array
    {
        $user = request()->user();
        abort_unless($user !== null, 403);

        $context = $this->sessions->tenantContextFor($user);
        abort_unless($context instanceof TenantContext, 403);

        $allowed = $this->permissions->hasTenantPermission($context, 'acs.events.view')
            || $this->permissions->hasTenantPermission($context, 'acs.health.view')
            || $this->permissions->hasTenantPermission($context, 'acs.configure');

        abort_unless($allowed, 403);

        $event = Event::query()
            ->where('tenant_id', $context->tenant->id)
            ->findOrFail($this->routeParam('event_id'));

        return [$context, $event];
    }
}
