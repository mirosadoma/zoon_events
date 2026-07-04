<?php

namespace App\Modules\Orders\Contracts;

interface OrderPersonalDataAnonymizer
{
    public function anonymize(string $tenantId, string $orderId, string $tombstone): void;
}
