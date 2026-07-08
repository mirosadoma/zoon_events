<?php

namespace App\Modules\IdentityVerification\Application\Support;

use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\Events\Contracts\PublicOrderHostAuthorizer;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\IdentityVerification\Infrastructure\Persistence\Models\IdentityConsent;
use App\Modules\Orders\Infrastructure\Persistence\Models\Order;
use App\Modules\Orders\Infrastructure\Persistence\Models\OrderItem;
use Illuminate\Http\Request;

final readonly class PublicOrderIdentityContext
{
    public function __construct(private PublicOrderHostAuthorizer $hosts) {}

    /** @return array{order:Order,attendee:Attendee} */
    public function resolve(Request $request, string $eventId, string $attendeeId): array
    {
        $token = (string) $request->header('X-Order-Access-Token');
        if ($token === '') {
            abort(404);
        }

        $attendee = Attendee::query()
            ->where('event_id', $eventId)
            ->where('id', $attendeeId)
            ->first();

        if ($attendee === null) {
            abort(404);
        }

        $order = Order::query()
            ->where('tenant_id', $attendee->tenant_id)
            ->where('event_id', $eventId)
            ->where('id', $attendee->order_id)
            ->first();

        if ($order === null
            || ! hash_equals($order->access_token_hash, hash('sha256', $token))
            || ! $this->hosts->allows($request->getHost(), $order->tenant_id, $order->event_id)) {
            abort(404);
        }

        $item = OrderItem::query()
            ->where('tenant_id', $order->tenant_id)
            ->where('event_id', $order->event_id)
            ->where('order_id', $order->id)
            ->where('attendee_id', $attendee->id)
            ->first();

        if ($item === null) {
            abort(404);
        }

        return compact('order', 'attendee');
    }

    /** @return array{order:Order,attendee:Attendee,event:Event} */
    public function resolveBySlugAndToken(Request $request, string $eventSlug, string $accessToken): array
    {
        if ($accessToken === '') {
            abort(404);
        }

        $event = Event::query()->where('slug', $eventSlug)->first();
        if ($event === null || ! $this->hosts->allows($request->getHost(), $event->tenant_id, $event->id)) {
            abort(404);
        }

        $tokenHash = hash('sha256', $accessToken);
        $order = Order::query()
            ->where('tenant_id', $event->tenant_id)
            ->where('event_id', $event->id)
            ->where('access_token_hash', $tokenHash)
            ->first();

        if ($order === null) {
            abort(404);
        }

        $item = OrderItem::query()
            ->where('tenant_id', $order->tenant_id)
            ->where('event_id', $order->event_id)
            ->where('order_id', $order->id)
            ->firstOrFail();

        $attendee = Attendee::query()
            ->where('tenant_id', $order->tenant_id)
            ->where('event_id', $order->event_id)
            ->findOrFail($item->attendee_id);

        return compact('order', 'attendee', 'event');
    }

    public function activeConsent(string $tenantId, string $eventId, string $attendeeId): ?IdentityConsent
    {
        return IdentityConsent::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('attendee_id', $attendeeId)
            ->whereNull('withdrawn_at')
            ->orderByDesc('consented_at')
            ->first();
    }
}
