<?php

namespace App\Modules\AccessControl\Domain\Events;

final readonly class AcsRuleCreated
{
    public function __construct(
        public string $tenantId,
        public string $eventId,
        public string $ruleId,
    ) {}
}
