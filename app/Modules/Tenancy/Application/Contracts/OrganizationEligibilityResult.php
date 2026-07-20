<?php

namespace App\Modules\Tenancy\Application\Contracts;

use App\Modules\Tenancy\Domain\OrganizationType;

final readonly class OrganizationEligibilityResult
{
    public function __construct(
        public bool $eligible,
        public ?OrganizationType $organizationType,
        public ?string $reason = null,
    ) {}
}
