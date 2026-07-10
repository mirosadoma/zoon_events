<?php

namespace App\Modules\Credentials\Application;

use App\Modules\Credentials\Application\Actions\IssueCredential;
use App\Modules\Credentials\Contracts\CredentialIssuer;
use App\Modules\Credentials\Domain\IssuedCredential;
use Carbon\CarbonImmutable;

final readonly class GatedCredentialIssuer implements CredentialIssuer
{
    public function __construct(private IssueCredential $issueCredential) {}

    public function issue(
        string $tenantId,
        string $eventId,
        string $attendeeId,
        string $ticketTypeId,
        CarbonImmutable $expiresAt,
    ): ?IssuedCredential {
        return $this->issueCredential->execute(
            $tenantId,
            $eventId,
            $attendeeId,
            $ticketTypeId,
            $expiresAt,
        );
    }
}
