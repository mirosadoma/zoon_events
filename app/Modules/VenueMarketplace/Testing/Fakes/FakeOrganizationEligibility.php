<?php

namespace App\Modules\VenueMarketplace\Testing\Fakes;

use App\Modules\Tenancy\Application\Contracts\OrganizationEligibility;
use App\Modules\Tenancy\Application\Contracts\OrganizationEligibilityResult;
use App\Modules\Tenancy\Domain\OrganizationType;

final class FakeOrganizationEligibility implements OrganizationEligibility
{
    public array $calls = [];

    public bool $fail = false;

    public OrganizationType $organizationType = OrganizationType::Hybrid;

    public function check(int $tenantId, string $requiredCapability): OrganizationEligibilityResult
    {
        $this->calls[] = compact('tenantId', 'requiredCapability');

        return new OrganizationEligibilityResult(
            ! $this->fail,
            $this->organizationType,
            $this->fail ? 'organization_type_not_eligible' : null,
        );
    }
}
