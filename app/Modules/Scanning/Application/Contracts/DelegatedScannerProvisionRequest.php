<?php

namespace App\Modules\Scanning\Application\Contracts;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class DelegatedScannerProvisionRequest
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
            || array_diff($capabilities, ['checkin.scan.submit']) !== []) {
            throw new InvalidArgumentException('Invalid delegated scanner provision request.');
        }
    }
}
