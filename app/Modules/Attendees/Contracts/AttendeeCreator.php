<?php

namespace App\Modules\Attendees\Contracts;

use App\Modules\Attendees\Domain\AttendeeRecord;

interface AttendeeCreator
{
    /** @param array{first_name:string,last_name:string,email:string,phone?:string} $identity */
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
    ): AttendeeRecord;
}
