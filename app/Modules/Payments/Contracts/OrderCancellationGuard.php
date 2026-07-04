<?php

namespace App\Modules\Payments\Contracts;

interface OrderCancellationGuard
{
    public function allows(string $tenantId, string $eventId, string $orderId): bool;
}
