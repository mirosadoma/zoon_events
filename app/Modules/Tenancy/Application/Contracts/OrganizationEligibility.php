<?php

namespace App\Modules\Tenancy\Application\Contracts;

interface OrganizationEligibility
{
    public const OWN_VENUES = 'own_venues';

    public const REQUEST_RENTALS = 'request_rentals';

    public function check(int $tenantId, string $requiredCapability): OrganizationEligibilityResult;
}
