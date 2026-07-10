<?php

namespace App\Modules\Notifications\Contracts;

interface NotificationDestinationAnonymizer
{
    public function anonymizeForAttendee(string $tenantId, string $attendeeId, string $tombstone): void;
}
