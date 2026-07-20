<?php

namespace App\Modules\Scanning\Application\Contracts;

use InvalidArgumentException;

final readonly class DelegatedScannerReleaseRequest
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
            throw new InvalidArgumentException('Invalid delegated scanner release request.');
        }
    }
}
