<?php

namespace App\Modules\AdminConsole\Application;

use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\Orders\Infrastructure\Persistence\Models\Order;
use App\Modules\Shared\Application\DataProtection\PersonalDataGuard;

final readonly class PersonalDataReader
{
    public function __construct(private PersonalDataGuard $guard) {}

    public function orderBuyerName(Order $order): ?string
    {
        if ($order->buyer_name_ciphertext === null || $order->encryption_key_id === null) {
            return null;
        }

        try {
            return $this->guard->decryptString(
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

            return trim($this->guard->decryptString(
                ['key_id' => $attendee->encryption_key_id, 'ciphertext' => $attendee->first_name_ciphertext],
                $scope,
            ).' '.$this->guard->decryptString(
                ['key_id' => $attendee->encryption_key_id, 'ciphertext' => $attendee->last_name_ciphertext],
                $scope,
            ));
        } catch (\Throwable) {
            return null;
        }
    }

    public function attendeeEmail(Attendee $attendee): ?string
    {
        return $this->decryptAttendeeField($attendee, $attendee->email_ciphertext);
    }

    public function attendeePhone(Attendee $attendee): ?string
    {
        return $this->decryptAttendeeField($attendee, $attendee->phone_ciphertext);
    }

    private function decryptAttendeeField(Attendee $attendee, ?string $ciphertext): ?string
    {
        if ($ciphertext === null || $attendee->encryption_key_id === null) {
            return null;
        }

        try {
            return $this->guard->decryptString(
                ['key_id' => $attendee->encryption_key_id, 'ciphertext' => $ciphertext],
                "{$attendee->tenant_id}:{$attendee->event_id}:attendee",
            );
        } catch (\Throwable) {
            return null;
        }
    }
}
