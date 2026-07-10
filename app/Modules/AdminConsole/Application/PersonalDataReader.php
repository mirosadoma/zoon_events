<?php

namespace App\Modules\AdminConsole\Application;

use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\Orders\Infrastructure\Persistence\Models\Order;
use App\Modules\Shared\Application\DataProtection\PersonalDataCipher;

final readonly class PersonalDataReader
{
    public function __construct(private PersonalDataCipher $cipher) {}

    public function orderBuyerName(Order $order): ?string
    {
        if ($order->buyer_name_ciphertext === null || $order->encryption_key_id === null) {
            return null;
        }

        try {
            return $this->cipher->decrypt(
                ['key_id' => $order->encryption_key_id, 'ciphertext' => $order->buyer_name_ciphertext],
                "{$order->tenant_id}:{$order->event_id}:order",
            );
        } catch (\Throwable) {
            return null;
        }
    }

    public function attendeeDisplayName(Attendee $attendee): ?string
    {
        if ($attendee->first_name_ciphertext === null || $attendee->encryption_key_id === null) {
            return null;
        }

        try {
            $scope = "{$attendee->tenant_id}:{$attendee->event_id}:attendee";

            return trim($this->cipher->decrypt(
                ['key_id' => $attendee->encryption_key_id, 'ciphertext' => $attendee->first_name_ciphertext],
                $scope,
            ).' '.$this->cipher->decrypt(
                ['key_id' => $attendee->encryption_key_id, 'ciphertext' => $attendee->last_name_ciphertext],
                $scope,
            ));
        } catch (\Throwable) {
            return null;
        }
    }
}
