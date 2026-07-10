<?php

namespace App\Modules\AccessControl\Domain\ValueObjects;

final readonly class AcsDecisionResult
{
    public function __construct(
        public string $decision,
        public string $reasonCode,
        public string $accessEventId,
        public ?string $scanEventId = null,
    ) {}
}
