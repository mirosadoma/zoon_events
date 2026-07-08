<?php

namespace App\Modules\AdminConsole\Http\Controllers\Tenant\Events;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\AdminConsole\Application\SessionContextBuilder;
use App\Modules\AdminConsole\ViewModels\Events\EventDashboardViewModel;
use App\Modules\AdminConsole\ViewModels\Events\EventSetupViewModel;
use App\Modules\Authorization\Application\PermissionEvaluator;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Registration\Infrastructure\Persistence\Models\RegistrationForm;
use App\Modules\Registration\Infrastructure\Persistence\Models\RegistrationFormVersion;
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
            'tenantId' => $context->tenant->id,
            'event' => [
                'id' => null,
                'slug' => '',
                'name' => ['en' => 'New event', 'ar' => 'فعالية جديدة'],
                'description' => ['en' => '', 'ar' => ''],
                'status' => 'draft',
                'tier' => 'public',
                'timezone' => 'Africa/Cairo',
                'start_at' => null,
                'end_at' => null,
                'registration_opens_at' => null,
                'registration_closes_at' => null,
                'capacity' => null,
                'location_name' => ['en' => '', 'ar' => ''],
                'location_address' => ['en' => '', 'ar' => ''],
                'brand_reference' => null,
                'domain_reference' => null,
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

        return Inertia::render('tenant/events/Detail', [
            ...$this->events->detail($this->event($context, $eventId)),
            'tenantId' => $context->tenant->id,
        ]);
    }

    public function edit(string $eventId): Response
    {
        $context = $this->authorizeTenant('event.manage');
        $event = $this->event($context, $eventId);

        return Inertia::render('tenant/events/EventSetup', [
            ...$this->setup->make(
                $event,
                $this->permissions->hasTenantPermission($context, 'event.manage'),
                $this->permissions->hasTenantPermission($context, 'event.publish'),
            ),
            'tenantId' => $context->tenant->id,
        ]);
    }

    public function registrationForm(string $eventId): Response
    {
        $context = $this->authorizeTenant('registration.manage');
        $event = $this->event($context, $eventId);
        $formState = $this->registrationFormState($context->tenant->id, $event);

        return Inertia::render('tenant/registration/Builder', [
            'event' => $this->events->detail($event)['event'],
            'tenantId' => $context->tenant->id,
            ...$formState,
        ]);
    }

    public function registrationPreview(string $eventId): Response
    {
        $context = $this->authorizeTenant('registration.manage');
        $event = $this->event($context, $eventId);
        $formState = $this->registrationFormState($context->tenant->id, $event);

        return Inertia::render('public/registration/Event', [
            'locale' => app()->getLocale() === 'ar' ? 'ar' : 'en',
            'event' => [
                'name' => ['en' => $event->name_en, 'ar' => $event->name_ar],
                'description' => ['en' => $event->description_en ?? '', 'ar' => $event->description_ar ?? ''],
                'branding' => [
                    'brand_reference' => $event->branding()->value('brand_reference'),
                    'domain_reference' => $event->branding()->value('domain_reference'),
                ],
            ],
            'form' => [
                'fields' => $formState['fields'],
                'privacy_notice_version' => $formState['privacyNoticeVersion'],
                'terms_version' => $formState['termsVersion'],
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

        return Inertia::render('tenant/events/Ticketing', $this->events->ticketing($event, $context->tenant->id, $tickets, $inventory));
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
        $ticketTypes = TicketType::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $event->id)
            ->orderBy('created_at')
            ->get();

        return Inertia::render('tenant/ticketing/PriceTiers', $this->events->priceTiers($event, $context->tenant->id, $tiers, $ticketTypes));
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

    /**
     * @return array{
     *     formName: string,
     *     privacyNoticeVersion: string,
     *     termsVersion: string,
     *     fields: list<array{key:string,type:string,label_en:string,label_ar:string,required:bool}>
     * }
     */
    private function registrationFormState(string $tenantId, Event $event): array
    {
        $form = RegistrationForm::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $event->id)
            ->first();

        $version = RegistrationFormVersion::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $event->id)
            ->where('status', 'draft')
            ->latest('version')
            ->first();

        if ($version === null && $event->active_form_version_id !== null) {
            $version = RegistrationFormVersion::query()
                ->where('tenant_id', $tenantId)
                ->where('event_id', $event->id)
                ->find($event->active_form_version_id);
        }

        $rawFields = is_array($version?->fields) ? $version->fields : [];

        $fields = collect($rawFields)->map(function (mixed $field, int $index): array {
            $row = is_array($field) ? $field : [];

            return [
                'key' => (string) ($row['key'] ?? "field_{$index}"),
                'type' => (string) ($row['type'] ?? 'text'),
                'label_en' => (string) ($row['label_en'] ?? ''),
                'label_ar' => (string) ($row['label_ar'] ?? ''),
                'required' => (bool) ($row['required'] ?? false),
            ];
        })->values()->all();

        return [
            'formName' => $form?->name ?? 'Registration form',
            'privacyNoticeVersion' => (string) ($version?->privacy_notice_version ?? 'v1'),
            'termsVersion' => (string) ($version?->terms_version ?? 'v1'),
            'fields' => $fields,
        ];
    }
}
