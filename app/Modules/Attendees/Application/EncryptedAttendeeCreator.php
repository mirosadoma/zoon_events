<?php

namespace App\Modules\Attendees\Application;

use App\Modules\Attendees\Contracts\AttendeeCreator;
use App\Modules\Attendees\Domain\AttendeeRecord;
use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\Shared\Application\DataProtection\BlindIndex;
use App\Modules\Shared\Application\DataProtection\PersonalDataCipher;

final readonly class EncryptedAttendeeCreator implements AttendeeCreator
{
    public function __construct(private PersonalDataCipher $cipher, private BlindIndex $indexes) {}

    public function create(string $tenantId, string $eventId, string $orderId, string $orderItemId, string $ticketTypeId, string $submissionId, array $identity, string $locale): AttendeeRecord
    {
        $scope = "{$tenantId}:{$eventId}:attendee";
        $encrypt = fn (string $value): string => $this->cipher->encrypt($value, $scope)['ciphertext'];
        $attendee = (new Attendee)->forceFill([
            'tenant_id' => $tenantId,
            'event_id' => $eventId,
            'order_id' => $orderId,
            'order_item_id' => $orderItemId,
            'ticket_type_id' => $ticketTypeId,
            'submission_id' => $submissionId,
            'first_name_ciphertext' => $encrypt($identity['first_name']),
            'last_name_ciphertext' => $encrypt($identity['last_name']),
            'email_ciphertext' => $encrypt($identity['email']),
            'phone_ciphertext' => isset($identity['phone']) ? $encrypt($identity['phone']) : null,
            'email_index' => $this->indexes->email($identity['email']),
            'phone_index' => isset($identity['phone']) ? $this->indexes->phone($identity['phone']) : null,
            'encryption_key_id' => $this->indexes->keyId(),
            'registration_status' => 'registered',
            'preferred_locale' => $locale,
            'registered_at' => now(),
        ]);
        $attendee->save();

        return new AttendeeRecord($attendee->id);
    }
}
