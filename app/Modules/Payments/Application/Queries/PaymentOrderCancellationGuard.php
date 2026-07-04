<?php

namespace App\Modules\Payments\Application\Queries;

use App\Modules\Payments\Contracts\OrderCancellationGuard;
use App\Modules\Payments\Infrastructure\Persistence\Models\PaymentAttempt;

final class PaymentOrderCancellationGuard implements OrderCancellationGuard
{
    public function allows(string $tenantId, string $eventId, string $orderId): bool
    {
        $status = PaymentAttempt::query()->where('tenant_id', $tenantId)->where('event_id', $eventId)
            ->where('order_id', $orderId)->latest('attempt_number')->value('status');

        return $status === null || in_array($status, ['failed', 'cancelled'], true);
    }
}
