<?php

namespace App\Modules\AdminConsole\Http\Controllers\Tenant\Events;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\AdminConsole\Application\SessionContextBuilder;
use App\Modules\AdminConsole\Http\Controllers\Tenant\Events\Concerns\ResolvesTenantEventFromRoute;
use App\Modules\AdminConsole\ViewModels\Events\EventDashboardViewModel;
use App\Modules\AdminConsole\ViewModels\Events\EventSetupReferenceData;
use App\Modules\AdminConsole\ViewModels\Events\EventSetupViewModel;
use App\Modules\Authorization\Application\PermissionEvaluator;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\IdentityVerification\Application\Actions\ViewIdentityDataAction;
use App\Modules\IdentityVerification\Application\Queries\PendingReviewQueue;
use App\Modules\IdentityVerification\Infrastructure\Persistence\Models\IdentityVerification;
use App\Modules\IdentityVerification\Infrastructure\Persistence\Models\IdentityVerificationRequirement;
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
    use ResolvesTenantEventFromRoute;

    public function __construct(
        private readonly SessionContextBuilder $sessions,
        private readonly PermissionEvaluator $permissions,
        private readonly EventDashboardViewModel $events,
        private readonly EventSetupViewModel $setup,
        private readonly EventSetupReferenceData $references,
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
            'tenantId' => (string) $context->tenant->id,
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
                'brand_reference' => null,
                'domain_reference' => null,
                'venues' => [],
                'readiness' => ['Save the event before publishing.'],
            ],
            'can' => [
                'manage' => $this->permissions->hasTenantPermission($context, 'event.manage'),
                'publish' => false,
            ],
            ...$this->references->toArray(),
        ]);
    }

    public function show(string $eventId): Response
    {
        $context = $this->authorizeTenant('event.view');

        return Inertia::render('tenant/events/Detail', [
            ...$this->events->detail($this->event($context, $eventId)),
            'tenantId' => (string) $context->tenant->id,
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
            'tenantId' => (string) $context->tenant->id,
            ...$this->references->toArray(),
        ]);
    }

    public function registrationForm(string $eventId): Response
    {
        $context = $this->authorizeTenant('registration.manage');
        $event = $this->event($context, $eventId);
        $formState = $this->registrationFormState($context->tenant->id, $event);

        return Inertia::render('tenant/registration/Builder', [
            'event' => $this->events->detail($event)['event'],
            'tenantId' => (string) $context->tenant->id,
            ...$formState,
        ]);
    }

    public function identityRequirements(string $eventId): Response
    {
        $context = $this->authorizeTenant('identity.configure');
        $event = $this->event($context, $eventId);
        $ticketTypes = TicketType::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $event->id)
            ->orderBy('created_at')
            ->get();
        $requirements = IdentityVerificationRequirement::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $event->id)
            ->orderByRaw('ticket_type_id IS NULL DESC')
            ->orderBy('ticket_type_id')
            ->get();

        return Inertia::render('tenant/identity/Requirements', [
            'tenantId' => (string) $context->tenant->id,
            'event' => $this->events->detail($event)['event'],
            'ticketTypes' => $ticketTypes->map(fn (TicketType $ticket): array => [
                'id' => (string) $ticket->id,
                'code' => $ticket->code,
                'name' => ['en' => $ticket->name_en, 'ar' => $ticket->name_ar],
            ])->values()->all(),
            'requirements' => $requirements->map(fn (IdentityVerificationRequirement $requirement): array => [
                'id' => (string) $requirement->id,
                'event_id' => (string) $requirement->event_id,
                'ticket_type_id' => $requirement->ticket_type_id !== null ? (string) $requirement->ticket_type_id : null,
                'level' => (string) $requirement->level,
                'face_fallback_enabled' => (bool) $requirement->face_fallback_enabled,
            ])->values()->all(),
            'canManage' => $this->permissions->hasTenantPermission($context, 'identity.configure'),
        ]);
    }

    public function identityReview(string $eventId): Response
    {
        $context = $this->authorizeTenant('identity.review');
        $event = $this->event($context, $eventId);
        $items = app(PendingReviewQueue::class)
            ->forEvent((string) $context->tenant->id, $event->id)
            ->map(fn ($row): array => [
                'id' => (string) $row->id,
                'attendee_id' => (string) $row->attendee_id,
                'method' => (string) $row->method,
                'status' => (string) $row->status,
                'provider_reference' => $row->provider_reference,
                'submitted_at' => $row->updated_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return Inertia::render('tenant/identity/ReviewQueue', [
            'tenantId' => (string) $context->tenant->id,
            'event' => $this->events->detail($event)['event'],
            'items' => $items,
            'canReview' => $this->permissions->hasTenantPermission($context, 'identity.review'),
        ]);
    }

    public function identityVerificationDetail(string $eventId, string $verificationId, ViewIdentityDataAction $viewAction): Response
    {
        $context = $this->authorizeTenant('identity.data.view');
        $event = $this->event($context, $eventId);
        $verification = IdentityVerification::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $event->id)
            ->findOrFail($verificationId);

        $detail = $viewAction->execute(
            $context,
            (string) $event->id,
            (string) $verification->attendee_id,
        );

        return Inertia::render('tenant/identity/VerificationDetail', [
            'tenantId' => (string) $context->tenant->id,
            'event' => $this->events->detail($event)['event'],
            'verificationId' => (string) $verification->id,
            'attendeeId' => (string) $verification->attendee_id,
            'detail' => $detail,
            'canManage' => $this->permissions->hasTenantPermission($context, 'identity.data.manage'),
        ]);
    }

    public function registrationPreview(string $eventId): Response
    {
        $context = $this->authorizeTenant('registration.manage');
        $event = $this->event($context, $eventId);
        $formState = $this->registrationFormState($context->tenant->id, $event);

        $version = RegistrationFormVersion::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $event->id)
            ->where('status', 'draft')
            ->latest('version')
            ->first();

        if ($version === null && $event->active_form_version_id !== null) {
            $version = RegistrationFormVersion::query()
                ->where('tenant_id', $context->tenant->id)
                ->where('event_id', $event->id)
                ->find($event->active_form_version_id);
        }

        $ticketTypes = TicketType::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $event->id)
            ->where('status', 'active')
            ->orderBy('created_at')
            ->get()
            ->map(fn (TicketType $ticket): array => [
                'id' => (string) $ticket->id,
                'code' => $ticket->code,
                'name' => ['en' => $ticket->name_en, 'ar' => $ticket->name_ar],
                'price_minor' => $ticket->base_price_minor,
                'currency' => $ticket->currency,
            ])
            ->values()
            ->all();

        return Inertia::render('public/registration/Event', [
            'locale' => app()->getLocale() === 'ar' ? 'ar' : 'en',
            'tenantId' => (string) $context->tenant->id,
            'event' => [
                'id' => $event->id,
                'slug' => $event->slug,
                'name' => ['en' => $event->name_en, 'ar' => $event->name_ar],
                'description' => ['en' => $event->description_en ?? '', 'ar' => $event->description_ar ?? ''],
                'start_at' => $event->start_at?->toIso8601String(),
                'end_at' => $event->end_at?->toIso8601String(),
                'branding' => [
                    'brand_reference' => $event->branding()->value('brand_reference'),
                    'domain_reference' => $event->branding()->value('domain_reference'),
                ],
            ],
            'form' => [
                'version_id' => $version?->id !== null ? (string) $version->id : null,
                'fields' => $formState['fields'],
                'privacy_notice_version' => $formState['privacyNoticeVersion'],
                'terms_version' => $formState['termsVersion'],
            ],
            'ticketTypes' => $ticketTypes,
            'isPreview' => true,
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
