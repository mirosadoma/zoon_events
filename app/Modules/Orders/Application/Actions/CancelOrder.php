<?php

namespace App\Modules\Orders\Application\Actions;

use App\Modules\Audit\Application\AuditWriter;
use App\Modules\Orders\Domain\OrderStatus;
use App\Modules\Orders\Infrastructure\Persistence\Models\Order;
use App\Modules\Payments\Contracts\OrderCancellationGuard;
use App\Modules\Shared\Http\Problems\Phase1Problem;
use App\Modules\Tenancy\Domain\Context\TenantContext;
use App\Modules\Ticketing\Contracts\TicketHoldReleaser;
use Illuminate\Support\Facades\DB;

final readonly class CancelOrder
{
    public function __construct(
        private OrderCancellationGuard $payments,
        private TicketHoldReleaser $holds,
        private AuditWriter $audit,
    ) {}

    public function execute(TenantContext $context, string $eventId, string $orderId, string $reason): Order
    {
        return DB::transaction(function () use ($context, $eventId, $orderId, $reason): Order {
            $order = Order::query()->where('tenant_id', $context->tenant->id)->where('event_id', $eventId)
                ->lockForUpdate()->findOrFail($orderId);
            if ($order->status === OrderStatus::Cancelled->value) {
                return $order;
            }
            if (! OrderStatus::from($order->status)->canTransitionTo(OrderStatus::Cancelled)
                || ! $this->payments->allows($order->tenant_id, $order->event_id, $order->id)) {
                throw Phase1Problem::make('order_cancellation_not_allowed');
            }
            $this->holds->release($order->tenant_id, $order->inventory_hold_id, 'order_cancelled');
            $order->forceFill(['status' => 'cancelled', 'cancelled_at' => now()])->save();
            $this->audit->writeTenant(
                'order.cancelled', 'succeeded', $context,
                targetType: 'order', targetId: $order->id,
                metadata: ['event_id' => $eventId, 'reason' => $reason],
            );

            return $order->refresh();
        });
    }
}
