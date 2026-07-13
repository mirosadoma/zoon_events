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
use App\Modules\Events\Application\Support\EventMediaPresenter;
use App\Modules\Events\Application\Support\PublicRegistrationEventPresenter;
use App\Modules\Events\Application\Support\ResolvesEventOrganizer;
use App\Modules\Events\Domain\EventRegistrationProfile;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Events\Infrastructure\Persistence\Models\EventAgendaItem;
use App\Modules\IdentityVerification\Application\Actions\ViewIdentityDataAction;
use App\Modules\IdentityVerification\Application\Queries\PendingReviewQueue;
use App\Modules\IdentityVerification\Infrastructure\Persistence\Models\IdentityVerification;
use App\Modules\IdentityVerification\Infrastructure\Persistence\Models\IdentityVerificationRequirement;
use App\Modules\Registration\Application\Support\RegistrationFieldPresenter;
use App\Modules\Registration\Domain\Fields\RegistrationSystemFields;
use App\Modules\Registration\Application\Queries\ResolvePublishedRegistrationForm;
use App\Modules\Registration\Infrastructure\Persistence\Models\RegistrationForm;
use App\Modules\Registration\Infrastructure\Persistence\Models\RegistrationFormVersion;
use App\Modules\Tenancy\Domain\Context\TenantContext;
use App\Modules\Ticketing\Infrastructure\Persistence\Models\PriceTier;
use App\Modules\Ticketing\Infrastructure\Persistence\Models\TicketInventory;
use App\Modules\Ticketing\Application\Queries\PublicTicketTypeCatalog;
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
        private readonly ResolvesEventOrganizer $organizers,
        private readonly EventMediaPresenter $media,
        private readonly PublicRegistrationEventPresenter $eventPages,
        private readonly RegistrationFieldPresenter $registrationFields,
        private readonly ResolvePublishedRegistrationForm $publishedForms,
        private readonly PublicTicketTypeCatalog $publicTickets,
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
                'tier' => 'corporate',
                'event_type' => 'seminar',
                'registration_mode' => 'free_registration',
                'timezone' => 'Africa/Cairo',
                'start_at' => null,
                'end_at' => null,
                'registration_opens_at' => null,
                'registration_closes_at' => null,
                'capacity' => null,
                'brand_reference' => null,
                'domain_reference' => null,
                'organizer_user_id' => null,
                'main_image' => null,
                'images' => [],
                'venues' => [],
                'event_type' => 'seminar',
                'registration_mode' => 'free_registration',
                'readiness' => ['Save the event before publishing.'],
            ],
            'eventPermissions' => [
                'manage' => $this->permissions->hasTenantPermission($context, 'event.manage'),
                'publish' => false,
            ],
            ...$this->references->toArray(),
            ...$this->organizerSetup($context),
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
            ...$this->organizerSetup($context),
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

    public function agenda(string $eventId): Response
    {
        $context = $this->authorizeTenant('event.manage');
        $event = $this->event($context, $eventId);
        $items = EventAgendaItem::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $event->id)
            ->orderBy('sort_order')
            ->orderBy('start_at')
            ->get()
            ->map(fn (EventAgendaItem $item): array => [
                'id' => (string) $item->id,
                'title_en' => $item->title_en,
                'title_ar' => $item->title_ar,
                'start_at' => $item->start_at?->toIso8601String(),
                'end_at' => $item->end_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return Inertia::render('tenant/events/Agenda', [
            'event' => $this->events->detail($event)['event'],
            'tenantId' => (string) $context->tenant->id,
            'items' => $items,
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
            ->whereIn('status', ['draft', 'published'])
            ->orderByDesc('version')
            ->first();

        if ($version === null) {
            $version = $this->publishedForms->forEvent($event);
        }

        $ticketTypes = $this->publicTickets->forEvent($event)
            ->map(fn (TicketType $ticket): array => [
                'id' => (string) $ticket->id,
                'code' => $ticket->code,
                'name' => ['en' => $ticket->name_en, 'ar' => $ticket->name_ar],
                'price_minor' => $ticket->base_price_minor,
                'currency' => $ticket->currency,
            ])
            ->values()
            ->all();

        $rawFields = is_array($version->fields) ? $version->fields : [];
        $previewFields = collect($rawFields)
            ->filter(fn (mixed $field): bool => is_array($field)
                && ($field['visibility'] ?? 'public') === 'public'
                && ($field['type'] ?? '') !== 'hidden')
            ->map(fn (array $field, int $index): array => $this->registrationFields->clientField($field, $index))
            ->values()
            ->all();

        return Inertia::render('public/registration/Event', [
            'locale' => app()->getLocale() === 'ar' ? 'ar' : 'en',
            'tenantId' => (string) $context->tenant->id,
            'event' => [
                ...$this->eventPages->heroEvent($event, true),
                'id' => $event->id,
            ],
            'form' => [
                'version_id' => (string) $version->id,
                'fields' => $previewFields,
                'privacy_notice_version' => $formState['privacyNoticeVersion'],
                'terms_version' => $formState['termsVersion'],
            ],
            'ticketTypes' => $ticketTypes,
            'requiresTicketSelection' => EventRegistrationProfile::requiresTicketConfiguration($event),
            'isPreview' => true,
        ]);
    }

    public function agendaPreview(string $eventId): Response
    {
        $context = $this->authorizeTenant('event.view');
        $event = $this->event($context, $eventId);
        $event->loadMissing('agendaItems');
        $locale = app()->getLocale() === 'ar' ? 'ar' : 'en';

        return Inertia::render('public/registration/Agenda', [
            'locale' => $locale,
            'isPreview' => true,
            'event' => $this->eventPages->heroEvent($event),
            'items' => $event->agendaItems
                ->map(fn (EventAgendaItem $item): array => [
                    'id' => (string) $item->id,
                    'title' => ['en' => $item->title_en, 'ar' => $item->title_ar],
                    'start_at' => $item->start_at?->toIso8601String(),
                    'end_at' => $item->end_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
            'registerUrl' => "/tenant/events/{$event->id}/registration-preview",
        ]);
    }

    public function ticketTypes(string $eventId): Response
    {
        $context = $this->authorizeTenant('ticketing.manage');
        $event = $this->event($context, $eventId);
        abort_unless(EventRegistrationProfile::requiresTicketConfiguration($event), 404);

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
        abort_unless(EventRegistrationProfile::requiresPriceTiers($event), 404);

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

    /** @return array{requiresOrganizerSelection:bool,organizerCandidates:list<array{id:string,name:string,email:string}>} */
    private function organizerSetup(TenantContext $context): array
    {
        $requiresOrganizerSelection = $this->organizers->requiresSelection($context);

        return [
            'requiresOrganizerSelection' => $requiresOrganizerSelection,
            'organizerCandidates' => $requiresOrganizerSelection
                ? $this->organizers->candidates($context->tenant->id)
                : [],
        ];
    }

    /**
     * @return array{
     *     formName: string,
     *     privacyNoticeVersion: string,
     *     termsVersion: string,
     *     fields: list<array<string,mixed>>,
     *     hasUnpublishedChanges: bool
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
            ->whereIn('status', ['draft', 'published'])
            ->orderByDesc('version')
            ->first();

        $publishedVersion = $event->active_form_version_id !== null
            ? RegistrationFormVersion::query()
                ->where('tenant_id', $tenantId)
                ->where('event_id', $event->id)
                ->find($event->active_form_version_id)
            : null;

        $rawFields = RegistrationSystemFields::enforce(is_array($version?->fields) ? $version->fields : []);

        $fields = collect($rawFields)->map(function (mixed $field, int $index): array {
            $row = is_array($field) ? $field : [];

            return $this->registrationFields->builderField($row, $index);
        })->values()->all();

        $hasUnpublishedChanges = $version !== null
            && $version->status === 'draft'
            && ($publishedVersion === null || (int) $version->version > (int) $publishedVersion->version);

        return [
            'formName' => $form?->name ?? 'Registration form',
            'privacyNoticeVersion' => (string) ($version?->privacy_notice_version ?? 'v1'),
            'termsVersion' => (string) ($version?->terms_version ?? 'v1'),
            'fields' => $fields,
            'hasUnpublishedChanges' => $hasUnpublishedChanges,
        ];
    }
}
