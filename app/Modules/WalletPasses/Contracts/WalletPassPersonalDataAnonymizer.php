<?php

namespace App\Modules\WalletPasses\Contracts;

interface WalletPassPersonalDataAnonymizer
{
    public function anonymizeForAttendee(string $tenantId, string $attendeeId): void;
}
