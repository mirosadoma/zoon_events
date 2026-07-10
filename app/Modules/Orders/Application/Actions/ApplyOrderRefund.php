<?php

namespace App\Modules\Orders\Application\Actions;

use App\Modules\Orders\Contracts\OrderRefundPort;
use App\Modules\Orders\Domain\RefundableOrder;
use App\Modules\Orders\Infrastructure\Persistence\Models\Order;

final class ApplyOrderRefund implements OrderRefundPort
{
    public function refundable(string $tenantId, string $eventId, string $orderId): RefundableOrder
    {
        $order = Order::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->findOrFail($orderId);

        return new RefundableOrder($order->id, $order->total_minor, $order->currency, $order->status);
    }

    public function applyRefund(string $orderId, int $cumulativeRefundedMinor): void
    {
        $order = Order::query()->lockForUpdate()->findOrFail($orderId);
        $full = $cumulativeRefundedMinor >= $order->total_minor;
        $order->forceFill([
            'status' => $full ? 'refunded' : 'partially_refunded',
            'refunded_at' => $full ? now() : null,
        ])->save();
    }
}
