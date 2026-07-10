<?php

namespace App\Modules\Orders\Contracts;

use App\Modules\Orders\Domain\ConfirmationOrderDetails;

interface ConfirmationOrderReader
{
    public function find(string $tenantId, string $eventId, string $orderId): ?ConfirmationOrderDetails;
}
