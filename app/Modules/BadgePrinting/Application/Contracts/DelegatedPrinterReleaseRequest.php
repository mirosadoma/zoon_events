<?php

namespace App\Modules\BadgePrinting\Application\Contracts;

use InvalidArgumentException;

final readonly class DelegatedPrinterReleaseRequest
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
            throw new InvalidArgumentException('Invalid delegated printer release request.');
        }
    }
}
