<?php

namespace App\Modules\AccessControl\Application\Contracts;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class DelegatedAcsProvisionRequest
{
    public function __construct(
        public string $organizerTenantId,
        public string $eventPublicId,
        public string $delegationPublicId,
        public string $assetPublicId,
        public string $assetType,
        public array $capabilities,
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
        public string $correlationId,
        public string $idempotencyKey,
    ) {
        if (! in_array($assetType, ['turnstile', 'security_gate', 'access_lane', 'access_zone'], true)
            || array_diff($capabilities, ['acs.configure']) !== []
            || $endsAt <= $startsAt || $idempotencyKey === '' || $correlationId === '') {
            throw new InvalidArgumentException('Invalid delegated ACS provision request.');
        }
    }
}
