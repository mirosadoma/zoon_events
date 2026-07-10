<?php

namespace App\Modules\AccessControl\Domain\ValueObjects;

final readonly class AcsIntegrationContext
{
    /**
     * @param  list<string>  $capabilities
     */
    public function __construct(
        public string $tenantId,
        public string $eventId,
        public array $capabilities,
    ) {}
}
