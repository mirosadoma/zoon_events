<?php

namespace App\Modules\VenueMarketplace\Testing\Fakes;

use App\Modules\AccessControl\Application\Contracts\DelegatedAcsAssetPort;
use App\Modules\AccessControl\Application\Contracts\DelegatedAcsPortResult;
use App\Modules\AccessControl\Application\Contracts\DelegatedAcsProvisionRequest;
use App\Modules\AccessControl\Application\Contracts\DelegatedAcsReleaseRequest;
use RuntimeException;

final class FakeDelegatedAcsAssetPort implements DelegatedAcsAssetPort
{
    public array $calls = [];

    public bool $fail = false;

    public function provision(DelegatedAcsProvisionRequest $request): DelegatedAcsPortResult
    {
        $this->record('provision', $request);

        return new DelegatedAcsPortResult('provisioned', $request->assetType, 'acs-resource', $request->capabilities);
    }

    public function release(DelegatedAcsReleaseRequest $request): DelegatedAcsPortResult
    {
        $this->record('release', $request);

        return new DelegatedAcsPortResult('released', 'acs', $request->resourcePublicReference);
    }

    private function record(string $operation, object $request): void
    {
        $this->calls[] = compact('operation', 'request');
        if ($this->fail) {
            throw new RuntimeException('fake_acs_failure');
        }
    }
}
