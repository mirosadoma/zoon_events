<?php

namespace App\Modules\Scanning\Contracts;

interface ScanEventPersonalDataAnonymizer
{
    public function anonymizeForAttendee(string $tenantId, string $attendeeId): void;
}
