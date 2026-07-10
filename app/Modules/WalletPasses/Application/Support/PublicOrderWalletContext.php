<?php

namespace App\Modules\WalletPasses\Application\Support;

use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\Credentials\Infrastructure\Persistence\Models\Credential;
use App\Modules\Events\Contracts\PublicOrderHostAuthorizer;
use App\Modules\Orders\Infrastructure\Persistence\Models\Order;
use App\Modules\Orders\Infrastructure\Persistence\Models\OrderItem;
use Illuminate\Http\Request;

final readonly class PublicOrderWalletContext
{
    public function __construct(private PublicOrderHostAuthorizer $hosts) {}

    /** @return array{order:Order,attendee:Attendee,credential:Credential} */
    public function resolve(Request $request, string $publicReference): array
    {
        $token = (string) $request->header('X-Order-Access-Token');
        $order = Order::query()->where('public_reference', $publicReference)->first();
        if ($order === null
            || ! hash_equals($order->access_token_hash, hash('sha256', $token))
            || ! $this->hosts->allows($request->getHost(), $order->tenant_id, $order->event_id)) {
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

        $credential = Credential::query()
            ->where('tenant_id', $order->tenant_id)
            ->where('event_id', $order->event_id)
            ->where('attendee_id', $attendee->id)
            ->where('status', 'active')
            ->firstOrFail();

        return compact('order', 'attendee', 'credential');
    }
}
