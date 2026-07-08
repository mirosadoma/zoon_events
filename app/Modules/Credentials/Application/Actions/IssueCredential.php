<?php

namespace App\Modules\Credentials\Application\Actions;

use App\Modules\Credentials\Application\CredentialIssuerService;
use App\Modules\Credentials\Domain\IssuedCredential;
use App\Modules\IdentityVerification\Application\Queries\IdentityGate;
use Carbon\CarbonImmutable;

final readonly class IssueCredential
{
    public function __construct(
        private IdentityGate $identityGate,
        private CredentialIssuerService $issuer,
    ) {}

    public function execute(
        string $tenantId,
        string $eventId,
        string $attendeeId,
        string $ticketTypeId,
        CarbonImmutable $expiresAt,
    ): ?IssuedCredential {
        $gate = $this->identityGate->evaluate($tenantId, $eventId, $attendeeId, 'credential');
        if (! $gate->satisfied) {
            return null;
        }

        return $this->issuer->issue($tenantId, $eventId, $attendeeId, $ticketTypeId, $expiresAt);
    }
}
