<?php

namespace App\Modules\Orders\Contracts;

use App\Modules\Orders\Domain\RefundableOrder;

interface OrderRefundPort
{
    public function refundable(string $tenantId, string $eventId, string $orderId): RefundableOrder;

    public function applyRefund(string $orderId, int $cumulativeRefundedMinor): void;
}
