<?php

namespace App\Modules\Attendees\Application;

use App\Modules\Attendees\Contracts\AttendeeCreator;
use App\Modules\Attendees\Domain\AttendeeRecord;
use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\Shared\Application\DataProtection\PersonalDataGuard;

final readonly class EncryptedAttendeeCreator implements AttendeeCreator
{
    public function __construct(private PersonalDataGuard $guard) {}

    public function create(
        string $tenantId,
        string $eventId,
        string $orderId,
        string $orderItemId,
        string $ticketTypeId,
        string $submissionId,
        array $identity,
        string $locale,
        ?string $eventVenueId = null,
    ): AttendeeRecord
    {
        $scope = "{$tenantId}:{$eventId}:attendee";
        $encrypt = fn (string $value): array => $this->guard->encryptString($value, $scope);
        $first = $encrypt($identity['first_name']);
        $last = $encrypt($identity['last_name']);
        $email = $encrypt($identity['email']);
        $phone = isset($identity['phone']) ? $encrypt($identity['phone']) : null;
        $attendee = (new Attendee)->forceFill([
            'tenant_id' => $tenantId,
            'event_id' => $eventId,
            'order_id' => $orderId,
            'order_item_id' => $orderItemId,
            'ticket_type_id' => $ticketTypeId,
            'submission_id' => $submissionId,
            'event_venue_id' => $eventVenueId !== null && $eventVenueId !== '' ? (int) $eventVenueId : null,
            'first_name_ciphertext' => $first['ciphertext'],
            'last_name_ciphertext' => $last['ciphertext'],
            'email_ciphertext' => $email['ciphertext'],
            'phone_ciphertext' => $phone['ciphertext'] ?? null,
            'email_index' => $this->guard->emailIndex($identity['email']),
            'phone_index' => isset($identity['phone']) ? $this->guard->phoneIndex($identity['phone']) : null,
            'encryption_key_id' => $first['key_id'],
            'registration_status' => 'registered',
            'invite_status' => 'registered',
            'preferred_locale' => $locale,
            'registered_at' => now(),
        ]);
        $attendee->save();

        return new AttendeeRecord($attendee->id);
    }
}
