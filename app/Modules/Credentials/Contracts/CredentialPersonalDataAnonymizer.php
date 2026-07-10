<?php

namespace App\Modules\Credentials\Contracts;

interface CredentialPersonalDataAnonymizer
{
    public function revokeForAttendee(string $tenantId, string $attendeeId): void;
}
