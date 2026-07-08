<?php

namespace App\Modules\AdminConsole\Http\Controllers\Tenant\Events;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\AdminConsole\Application\SessionContextBuilder;
use App\Modules\AdminConsole\ViewModels\Events\EventDashboardViewModel;
use App\Modules\AdminConsole\ViewModels\Events\EventSetupViewModel;
use App\Modules\Authorization\Application\PermissionEvaluator;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Tenancy\Domain\Context\TenantContext;
use App\Modules\Ticketing\Infrastructure\Persistence\Models\PriceTier;
use App\Modules\Ticketing\Infrastructure\Persistence\Models\TicketInventory;
use App\Modules\Ticketing\Infrastructure\Persistence\Models\TicketType;
use Inertia\Inertia;
use Inertia\Response;

final class EventDashboardController extends Controller
{
    public function __construct(
        private readonly SessionContextBuilder $sessions,
        private readonly PermissionEvaluator $permissions,
        private readonly EventDashboardViewModel $events,
        private readonly EventSetupViewModel $setup,
    ) {}

    public function index(): Response
    {
        $context = $this->authorizeTenant('event.view');
        $events = Event::query()
            ->where('tenant_id', $context->tenant->id)
            ->latest('created_at')
            ->limit(100)
            ->get();

        return Inertia::render('tenant/events/List', $this->events->index($events));
    }

    public function create(): Response
    {
        $context = $this->authorizeTenant('event.manage');

        return Inertia::render('tenant/events/EventSetup', [
            'event' => [
                'id' => null,
                'name' => ['en' => 'New event', 'ar' => 'فعالية جديدة'],
                'status' => 'draft',
                'tier' => 'public',
                'readiness' => ['Save the event before publishing.'],
            ],
            'can' => [
                'manage' => $this->permissions->hasTenantPermission($context, 'event.manage'),
                'publish' => false,
            ],
        ]);
    }

    public function show(string $eventId): Response
    {
        $context = $this->authorizeTenant('event.view');

        return Inertia::render('tenant/events/Detail', $this->events->detail($this->event($context, $eventId)));
    }

    public function edit(string $eventId): Response
    {
        $context = $this->authorizeTenant('event.manage');
        $event = $this->event($context, $eventId);

        return Inertia::render('tenant/events/EventSetup', $this->setup->make(
            $event,
            $this->permissions->hasTenantPermission($context, 'event.manage'),
            $this->permissions->hasTenantPermission($context, 'event.publish'),
        ));
    }

    public function registrationForm(string $eventId): Response
    {
        $context = $this->authorizeTenant('registration.manage');

        return Inertia::render('tenant/registration/Builder', [
            'event' => $this->events->detail($this->event($context, $eventId))['event'],
            'fields' => [],
        ]);
    }

    public function registrationPreview(string $eventId): Response
    {
        $context = $this->authorizeTenant('registration.manage');
        $event = $this->event($context, $eventId);

        return Inertia::render('public/registration/Event', [
            'locale' => app()->getLocale() === 'ar' ? 'ar' : 'en',
            'event' => [
                'name' => ['en' => $event->name_en, 'ar' => $event->name_ar],
                'description' => ['en' => $event->description_en ?? '', 'ar' => $event->description_ar ?? ''],
                'branding' => ['brand_reference' => $event->branding()->value('brand_reference')],
            ],
            'form' => [
                'fields' => [],
                'privacy_notice_version' => 'preview',
                'terms_version' => 'preview',
            ],
        ]);
    }

    public function ticketTypes(string $eventId): Response
    {
        $context = $this->authorizeTenant('ticketing.manage');
        $event = $this->event($context, $eventId);
        $tickets = TicketType::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $event->id)
            ->orderBy('created_at')
            ->get();
        $inventory = TicketInventory::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $event->id)
            ->get()
            ->keyBy('ticket_type_id');

        return Inertia::render('tenant/events/Ticketing', $this->events->ticketing($event, $tickets, $inventory));
    }

    public function priceTiers(string $eventId): Response
    {
        $context = $this->authorizeTenant('ticketing.manage');
        $event = $this->event($context, $eventId);
        $tiers = PriceTier::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $event->id)
            ->orderBy('priority')
            ->orderBy('starts_at')
            ->get();

        return Inertia::render('tenant/ticketing/PriceTiers', $this->events->priceTiers($event, $tiers));
    }

    private function authorizeTenant(string $permission): TenantContext
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        $context = $this->sessions->tenantContextFor($user);
        abort_unless($context instanceof TenantContext, 403);
        abort_unless($this->permissions->hasTenantPermission($context, $permission), 403);

        return $context;
    }

    private function event(TenantContext $context, string $eventId): Event
    {
        return Event::query()
            ->where('tenant_id', $context->tenant->id)
            ->findOrFail($eventId);
    }
}
