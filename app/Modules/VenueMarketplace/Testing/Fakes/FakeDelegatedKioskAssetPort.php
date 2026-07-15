<?php

namespace App\Modules\VenueMarketplace\Testing\Fakes;

use App\Modules\Kiosk\Application\Contracts\DelegatedKioskAssetPort;
use App\Modules\Kiosk\Application\Contracts\DelegatedKioskPortResult;
use App\Modules\Kiosk\Application\Contracts\DelegatedKioskProvisionRequest;
use App\Modules\Kiosk\Application\Contracts\DelegatedKioskReleaseRequest;
use RuntimeException;

final class FakeDelegatedKioskAssetPort implements DelegatedKioskAssetPort
{
    public array $calls = [];

    public bool $fail = false;

    public function provision(DelegatedKioskProvisionRequest $request): DelegatedKioskPortResult
    {
        $this->record('provision', $request);

        return new DelegatedKioskPortResult('provisioned', 'kiosk', 'kiosk-resource', $request->capabilities);
    }

    public function release(DelegatedKioskReleaseRequest $request): DelegatedKioskPortResult
    {
        $this->record('release', $request);

        return new DelegatedKioskPortResult('released', 'kiosk', $request->resourcePublicReference);
    }

    private function record(string $operation, object $request): void
    {
        $this->calls[] = compact('operation', 'request');
        if ($this->fail) {
            throw new RuntimeException('fake_kiosk_failure');
        }
    }
}
