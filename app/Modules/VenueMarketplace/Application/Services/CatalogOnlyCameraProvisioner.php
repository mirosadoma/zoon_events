<?php

namespace App\Modules\VenueMarketplace\Application\Services;

final class CatalogOnlyCameraProvisioner
{
    /** @return array{status: string, resourceType: string, resourcePublicReference: null, acceptedCapabilities: list<string>, reason: null} */
    public function provision(): array
    {
        return [
            'status' => 'not_applicable',
            'resourceType' => 'camera',
            'resourcePublicReference' => null,
            'acceptedCapabilities' => [],
            'reason' => null,
        ];
    }

    /** @return array{status: string, resourceType: string, resourcePublicReference: null, acceptedCapabilities: list<string>, reason: null} */
    public function release(): array
    {
        return [
            'status' => 'not_applicable',
            'resourceType' => 'camera',
            'resourcePublicReference' => null,
            'acceptedCapabilities' => [],
            'reason' => null,
        ];
    }
}
