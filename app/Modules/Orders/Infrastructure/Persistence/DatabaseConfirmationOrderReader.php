<?php

namespace App\Modules\Orders\Infrastructure\Persistence;

use App\Modules\Orders\Contracts\ConfirmationOrderReader;
use App\Modules\Orders\Domain\ConfirmationOrderDetails;
use App\Modules\Orders\Infrastructure\Persistence\Models\Order;

final class DatabaseConfirmationOrderReader implements ConfirmationOrderReader
{
    public function find(string $tenantId, string $eventId, string $orderId): ?ConfirmationOrderDetails
    {
        $order = Order::query()->where('tenant_id', $tenantId)->where('event_id', $eventId)
            ->whereKey($orderId)->first(['public_reference']);

        return $order ? new ConfirmationOrderDetails($order->public_reference) : null;
    }
}
