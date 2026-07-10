<?php

namespace App\Modules\Orders\Infrastructure\Persistence;

use App\Modules\Orders\Contracts\OrderPersonalDataAnonymizer;
use App\Modules\Orders\Infrastructure\Persistence\Models\Order;

final class DatabaseOrderPersonalDataAnonymizer implements OrderPersonalDataAnonymizer
{
    public function anonymize(string $tenantId, string $orderId, string $tombstone): void
    {
        Order::query()->where('tenant_id', $tenantId)->whereKey($orderId)->update([
            'buyer_name_ciphertext' => 'anonymized',
            'buyer_email_ciphertext' => 'anonymized',
            'buyer_phone_ciphertext' => null,
            'buyer_email_index' => $tombstone,
            'buyer_phone_index' => null,
            'encryption_key_id' => 'anonymized',
            'updated_at' => now(),
        ]);
    }
}
