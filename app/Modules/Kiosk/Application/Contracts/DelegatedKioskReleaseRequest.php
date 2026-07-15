<?php

namespace App\Modules\Kiosk\Application\Contracts;

use InvalidArgumentException;

final readonly class DelegatedKioskReleaseRequest
{
    public function __construct(
        public string $organizerTenantId,
        public string $eventPublicId,
        public string $delegationPublicId,
        public string $assetPublicId,
        public string $resourcePublicReference,
        public string $correlationId,
        public string $idempotencyKey,
    ) {
        if ($resourcePublicReference === '' || $correlationId === '' || $idempotencyKey === '') {
            throw new InvalidArgumentException('Invalid delegated kiosk release request.');
        }
    }
}
