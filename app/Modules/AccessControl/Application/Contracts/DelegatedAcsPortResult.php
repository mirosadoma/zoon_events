<?php

namespace App\Modules\AccessControl\Application\Contracts;

use InvalidArgumentException;

final readonly class DelegatedAcsPortResult
{
    public function __construct(
        public string $status,
        public string $resourceType,
        public ?string $resourcePublicReference,
        public array $acceptedCapabilities = [],
        public ?string $reason = null,
    ) {
        if (! in_array($status, ['provisioned', 'degraded', 'released', 'not_applicable'], true)) {
            throw new InvalidArgumentException('Invalid ACS port result.');
        }
    }
}
