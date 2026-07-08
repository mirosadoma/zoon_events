<?php

namespace App\Modules\Credentials\Contracts;

use App\Modules\Credentials\Domain\IssuedCredential;
use Carbon\CarbonImmutable;

interface CredentialIssuer
{
    public function issue(
        string $tenantId,
        string $eventId,
        string $attendeeId,
        string $ticketTypeId,
        CarbonImmutable $expiresAt,
    ): ?IssuedCredential;
}
