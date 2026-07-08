<?php

namespace App\Modules\AdminConsole\Http\Controllers\Tenant\Events;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\AdminConsole\Application\SessionContextBuilder;
use App\Modules\AdminConsole\ViewModels\Attendees\AttendeeDetailViewModel;
use App\Modules\AdminConsole\ViewModels\Credentials\CredentialDetailViewModel;
use App\Modules\AdminConsole\ViewModels\Orders\OrderDetailViewModel;
use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\Authorization\Application\PermissionEvaluator;
use App\Modules\Credentials\Infrastructure\Persistence\Models\Credential;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Notifications\Infrastructure\Persistence\Models\Notification;
use App\Modules\Orders\Infrastructure\Persistence\Models\Order;
use App\Modules\Orders\Infrastructure\Persistence\Models\OrderItem;
use App\Modules\Tenancy\Domain\Context\TenantContext;
use Inertia\Inertia;
use Inertia\Response;

final class EventOperationsController extends Controller
{
    public function __construct(
        private readonly SessionContextBuilder $sessions,
        private readonly PermissionEvaluator $permissions,
        private readonly OrderDetailViewModel $orders,
        private readonly AttendeeDetailViewModel $attendees,
        private readonly CredentialDetailViewModel $credentials,
    ) {}

    public function orders(string $eventId): Response
    {
        $context = $this->authorizeTenant('order.view');
        $event = $this->event($context, $eventId);
        $orders = Order::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $event->id)
            ->latest('created_at')
            ->limit(200)
            ->get();
        $notificationStatuses = $this->latestNotificationStatuses($context, $event->id, $orders->pluck('id')->all());

        return Inertia::render('tenant/events/Orders', $this->orders->index($event, $orders, $notificationStatuses));
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
                'label' => substr($attendee->id, -8),
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

    public function attendees(string $eventId): Response
    {
        $context = $this->authorizeTenant('attendee.view');
        $event = $this->event($context, $eventId);
        $attendees = Attendee::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $event->id)
            ->latest('registered_at')
            ->limit(200)
            ->get();
        $credentialStatuses = $this->credentialStatusesForAttendees($context, $event->id, $attendees->pluck('id')->all());

        return Inertia::render('tenant/events/Attendees', $this->attendees->index($event, $attendees, $credentialStatuses));
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
            'tenantId' => $context->tenant->id,
        ]);
    }

    public function credentials(string $eventId): Response
    {
        $context = $this->authorizeTenant('credential.view');
        $event = $this->event($context, $eventId);

        $credentials = Credential::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $event->id)
            ->latest('issued_at')
            ->limit(200)
            ->get();

        return Inertia::render('tenant/events/Credentials', $this->credentials->index($event, $credentials));
    }

    public function credentialShow(string $eventId, string $credentialId): Response
    {
        $context = $this->authorizeTenant('credential.view');
        $event = $this->event($context, $eventId);
        $credential = $this->credential($context, $event, $credentialId);

        return Inertia::render('tenant/credentials/Detail', [
            ...$this->credentials->detail($event, $credential),
            'tenantId' => $context->tenant->id,
        ]);
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

    private function order(TenantContext $context, Event $event, string $orderId): Order
    {
        return Order::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $event->id)
            ->findOrFail($orderId);
    }

    private function attendee(TenantContext $context, Event $event, string $attendeeId): Attendee
    {
        return Attendee::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $event->id)
            ->findOrFail($attendeeId);
    }

    private function credential(TenantContext $context, Event $event, string $credentialId): Credential
    {
        return Credential::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $event->id)
            ->findOrFail($credentialId);
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
