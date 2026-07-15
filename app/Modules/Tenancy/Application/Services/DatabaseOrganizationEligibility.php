<?php

namespace App\Modules\Tenancy\Application\Services;

use App\Modules\Tenancy\Application\Contracts\OrganizationEligibility;
use App\Modules\Tenancy\Application\Contracts\OrganizationEligibilityResult;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\Tenant;
use InvalidArgumentException;

final class DatabaseOrganizationEligibility implements OrganizationEligibility
{
    public function check(int $tenantId, string $requiredCapability): OrganizationEligibilityResult
    {
        if (! in_array($requiredCapability, [self::OWN_VENUES, self::REQUEST_RENTALS], true)) {
            throw new InvalidArgumentException('Unknown organization capability.');
        }

        $tenant = Tenant::query()->find($tenantId);

        if ($tenant === null) {
            return new OrganizationEligibilityResult(false, null, 'tenant_not_found');
        }

        if (! $tenant->status->isActive()) {
            return new OrganizationEligibilityResult(false, $tenant->organization_type, 'tenant_inactive');
        }

        $eligible = match ($requiredCapability) {
            self::OWN_VENUES => $tenant->organization_type->mayOwnVenues(),
            self::REQUEST_RENTALS => $tenant->organization_type->mayRequestRentals(),
        };

        return new OrganizationEligibilityResult(
            $eligible,
            $tenant->organization_type,
            $eligible ? null : 'organization_type_not_eligible',
        );
    }
}
