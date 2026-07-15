<?php

namespace App\Modules\Kiosk\Application\Contracts;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class DelegatedKioskProvisionRequest
{
    public function __construct(
        public string $organizerTenantId,
        public string $eventPublicId,
        public string $delegationPublicId,
        public string $assetPublicId,
        public array $capabilities,
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
        public string $correlationId,
        public string $idempotencyKey,
    ) {
        if ($endsAt <= $startsAt || $idempotencyKey === '' || $correlationId === ''
            || array_diff($capabilities, ['kiosk.manage']) !== []) {
            throw new InvalidArgumentException('Invalid delegated kiosk provision request.');
        }
    }
}
