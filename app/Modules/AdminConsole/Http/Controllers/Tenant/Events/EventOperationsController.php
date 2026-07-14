<?php

namespace App\Modules\AdminConsole\Http\Controllers\Tenant\Events;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\AdminConsole\Application\Exports\AttendeesExcelExport;
use App\Modules\AdminConsole\Application\PersonalDataReader;
use App\Modules\AdminConsole\Application\Queries\ListEventAttendeesQuery;
use App\Modules\AdminConsole\Application\SessionContextBuilder;
use App\Modules\AdminConsole\Application\Support\InertiaListPaginator;
use App\Modules\AdminConsole\Http\Controllers\Tenant\Events\Concerns\ResolvesTenantEventFromRoute;
use App\Modules\AdminConsole\ViewModels\Attendees\AttendeeDetailViewModel;
use App\Modules\AdminConsole\ViewModels\Credentials\CredentialDetailViewModel;
use App\Modules\AdminConsole\ViewModels\Orders\OrderDetailViewModel;
use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\Authorization\Application\PermissionEvaluator;
use App\Modules\Credentials\Infrastructure\Persistence\Models\Credential;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\IdentityVerification\Application\Queries\IdentityGate;
use App\Modules\Notifications\Infrastructure\Persistence\Models\Notification;
use App\Modules\Orders\Infrastructure\Persistence\Models\Order;
use App\Modules\Orders\Infrastructure\Persistence\Models\OrderItem;
use App\Modules\Tenancy\Domain\Context\TenantContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class EventOperationsController extends Controller
{
    use ResolvesTenantEventFromRoute;

    public function __construct(
        private readonly SessionContextBuilder $sessions,
        private readonly PermissionEvaluator $permissions,
        private readonly PersonalDataReader $personalData,
        private readonly OrderDetailViewModel $orders,
        private readonly AttendeeDetailViewModel $attendees,
        private readonly CredentialDetailViewModel $credentials,
        private readonly ListEventAttendeesQuery $listAttendees,
        private readonly AttendeesExcelExport $attendeesExcelExport,
    ) {}

    public function orders(Request $request, string $eventId): Response
    {
        $context = $this->authorizeTenant('order.view');
        $event = $this->event($context, $eventId);
        $filters = $this->orderFilters($request);

        $query = Order::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $event->id)
            ->when($filters['status'] !== '', fn ($builder) => $builder->where('status', $filters['status']))
            ->when($filters['search'] !== '', function ($builder) use ($filters): void {
                $needle = '%'.$filters['search'].'%';
                $builder->where(function ($inner) use ($needle, $filters): void {
                    $inner->where('public_reference', 'like', $needle)
                        ->orWhere('id', $filters['search']);
                });
            })
            ->latest('created_at')
            ->orderByDesc('id');

        $result = InertiaListPaginator::paginate($query, $request);
        $notificationStatuses = $this->latestNotificationStatuses($context, $event->id, $result['items']->pluck('id')->all());

        return Inertia::render('tenant/events/Orders', $this->orders->index(
            $event,
            $result['items'],
            $notificationStatuses,
            $filters,
            $result['pagination'],
        ));
    }

    public function orderShow(string $eventId, string $orderId): Response
    {
        $context = $this->authorizeTenant('order.view');
        $event = $this->event($context, $eventId);
        $order = $this->order($context, $event, $orderId);
        $items = OrderItem::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $event->id)
            ->where('order_id', $order->id)
            ->get();
        $attendees = Attendee::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $event->id)
            ->where('order_id', $order->id)
            ->get()
            ->map(fn (Attendee $attendee): array => [
                'id' => $attendee->id,
                'checkin_status' => $attendee->checkin_status,
                'label' => $this->personalData->attendeeDisplayName($attendee) ?: substr($attendee->id, -8),
            ])
            ->values()
            ->all();
        $notificationStatuses = $this->latestNotificationStatuses($context, $event->id, [$order->id]);

        return Inertia::render('tenant/orders/Detail', $this->orders->detail(
            $event,
            $order,
            $items,
            $attendees,
            $notificationStatuses[$order->id] ?? null,
        ));
    }

    public function attendees(Request $request, string $eventId): Response
    {
        $context = $this->authorizeTenant('attendee.view');
        $event = $this->event($context, $eventId);
        $filters = $this->attendeeFilters($request);
        $result = $this->listAttendees->paginate(
            (string) $context->tenant->id,
            (string) $event->id,
            $filters['search'] !== '' ? $filters['search'] : null,
            $filters['status'] !== '' ? $filters['status'] : null,
            (int) $request->integer('page', 1),
        );
        $credentialStatuses = $this->credentialStatusesForAttendees(
            $context,
            $event->id,
            $result['attendees']->pluck('id')->all(),
        );

        return Inertia::render('tenant/events/Attendees', $this->attendees->index(
            $event,
            $result['attendees'],
            $credentialStatuses,
            $filters,
            [
                'page' => $result['page'],
                'per_page' => $result['per_page'],
                'total' => $result['total'],
                'last_page' => $result['last_page'],
            ],
        ));
    }

    public function attendeesExport(Request $request, string $eventId): StreamedResponse
    {
        $context = $this->authorizeTenant('attendee.view');
        $event = $this->event($context, $eventId);
        $filters = $this->attendeeFilters($request);
        $attendees = $this->listAttendees->forExport(
            (string) $context->tenant->id,
            (string) $event->id,
            $filters['search'] !== '' ? $filters['search'] : null,
            $filters['status'] !== '' ? $filters['status'] : null,
        );
        $credentialStatuses = $this->credentialStatusesForAttendees(
            $context,
            $event->id,
            $attendees->pluck('id')->all(),
        );

        $slug = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $event->slug ?? (string) $event->id) ?: (string) $event->id;
        $filename = 'attendees-'.$slug.'-'.now()->format('Ymd-His').'.xlsx';

        return $this->attendeesExcelExport->download($attendees, $credentialStatuses, $filename);
    }

    public function attendeeShow(string $eventId, string $attendeeId): Response
    {
        $context = $this->authorizeTenant('attendee.view');
        $event = $this->event($context, $eventId);
        $attendee = $this->attendee($context, $event, $attendeeId);
        $credential = Credential::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $event->id)
            ->where('attendee_id', $attendee->id)
            ->whereNull('superseded_by_id')
            ->latest('issued_at')
            ->first();

        return Inertia::render('tenant/attendees/Detail', [
            ...$this->attendees->detail($event, $attendee, $credential),
            'tenantId' => (string) $context->tenant->id,
            'identity' => $this->identityState($context->tenant->id, $event->id, $attendee->id, 'credential'),
        ]);
    }

    public function credentials(Request $request, string $eventId): Response
    {
        $context = $this->authorizeTenant('credential.view');
        $event = $this->event($context, $eventId);
        $filters = $this->credentialFilters($request);

        $query = Credential::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $event->id)
            ->when($filters['status'] !== '', fn ($builder) => $builder->where('status', $filters['status']))
            ->when($filters['search'] !== '', function ($builder) use ($filters): void {
                $needle = '%'.$filters['search'].'%';
                $builder->where(function ($inner) use ($needle, $filters): void {
                    $inner->where('id', 'like', $needle)
                        ->orWhere('attendee_id', 'like', $needle)
                        ->orWhere('attendee_id', $filters['search']);
                });
            })
            ->latest('issued_at')
            ->orderByDesc('id');

        $result = InertiaListPaginator::paginate($query, $request);

        return Inertia::render('tenant/events/Credentials', $this->credentials->index(
            $event,
            $result['items'],
            $filters,
            $result['pagination'],
        ));
    }

    public function credentialShow(string $eventId, string $credentialId): Response
    {
        $context = $this->authorizeTenant('credential.view');
        $event = $this->event($context, $eventId);
        $credential = $this->credential($context, $event, $credentialId);

        return Inertia::render('tenant/credentials/Detail', [
            ...$this->credentials->detail($event, $credential),
            'tenantId' => (string) $context->tenant->id,
            'identity' => $this->identityState($context->tenant->id, $event->id, $credential->attendee_id, 'credential'),
        ]);
    }

    /** @return array{status:string,pending:bool,reason_code:?string,requirement_level:string} */
    private function identityState(string $tenantId, string $eventId, string $attendeeId, string $boundary): array
    {
        $gate = app(IdentityGate::class)->evaluate($tenantId, $eventId, $attendeeId, $boundary);

        return [
            'status' => $gate->status,
            'pending' => ! $gate->satisfied,
            'reason_code' => $gate->reasonCode,
            'requirement_level' => $gate->requirementLevel,
        ];
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
     * @return array<string, mixed>
     */
    private function order(TenantContext $context, Event $event, string $orderId): Order
    {
        return Order::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $event->id)
            ->findOrFail($this->routeParamOrNull('order_id') ?? $orderId);
    }

    private function attendee(TenantContext $context, Event $event, string $attendeeId): Attendee
    {
        return Attendee::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $event->id)
            ->findOrFail($this->routeParamOrNull('attendee_id') ?? $attendeeId);
    }

    private function credential(TenantContext $context, Event $event, string $credentialId): Credential
    {
        return Credential::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $event->id)
            ->findOrFail($this->routeParamOrNull('credential_id') ?? $credentialId);
    }

    /**
     * @param  list<string>  $orderIds
     * @return array<string, string>
     */
    private function latestNotificationStatuses(TenantContext $context, string $eventId, array $orderIds): array
    {
        if ($orderIds === []) {
            return [];
        }

        $rows = Notification::query()
            ->select(['order_id', 'status'])
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $eventId)
            ->whereIn('order_id', $orderIds)
            ->orderByDesc('created_at')
            ->get()
            ->unique('order_id');

        return $rows->pluck('status', 'order_id')->all();
    }

    /**
     * @return array{search: string, status: string}
     */
    private function attendeeFilters(Request $request): array
    {
        $status = trim((string) $request->query('status', ''));
        if (! in_array($status, ['not_checked_in', 'checked_in'], true)) {
            $status = '';
        }

        return [
            'search' => trim((string) $request->query('search', '')),
            'status' => $status,
        ];
    }

    /**
     * @return array{search: string, status: string}
     */
    private function orderFilters(Request $request): array
    {
        $status = trim((string) $request->query('status', ''));
        $allowed = ['draft', 'pending_payment', 'paid', 'failed', 'cancelled', 'refunded', 'partially_refunded'];
        if (! in_array($status, $allowed, true)) {
            $status = '';
        }

        return [
            'search' => trim((string) $request->query('search', '')),
            'status' => $status,
        ];
    }

    /**
     * @return array{search: string, status: string}
     */
    private function credentialFilters(Request $request): array
    {
        $status = trim((string) $request->query('status', ''));
        if (! in_array($status, ['active', 'revoked', 'expired', 'superseded'], true)) {
            $status = '';
        }

        return [
            'search' => trim((string) $request->query('search', '')),
            'status' => $status,
        ];
    }

    /**
     * @param  list<string>  $attendeeIds
     * @return array<string, string>
     */
    private function credentialStatusesForAttendees(TenantContext $context, string $eventId, array $attendeeIds): array
    {
        if ($attendeeIds === []) {
            return [];
        }

        return Credential::query()
            ->select(['attendee_id', 'status'])
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $eventId)
            ->whereIn('attendee_id', $attendeeIds)
            ->whereNull('superseded_by_id')
            ->get()
            ->pluck('status', 'attendee_id')
            ->all();
    }
}
