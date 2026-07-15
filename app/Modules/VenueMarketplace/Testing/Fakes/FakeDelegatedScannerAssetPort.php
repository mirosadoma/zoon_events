<?php

namespace App\Modules\VenueMarketplace\Testing\Fakes;

use App\Modules\Scanning\Application\Contracts\DelegatedScannerAssetPort;
use App\Modules\Scanning\Application\Contracts\DelegatedScannerPortResult;
use App\Modules\Scanning\Application\Contracts\DelegatedScannerProvisionRequest;
use App\Modules\Scanning\Application\Contracts\DelegatedScannerReleaseRequest;
use RuntimeException;

final class FakeDelegatedScannerAssetPort implements DelegatedScannerAssetPort
{
    public array $calls = [];

    public bool $fail = false;

    public function provision(DelegatedScannerProvisionRequest $request): DelegatedScannerPortResult
    {
        $this->record('provision', $request);

        return new DelegatedScannerPortResult('provisioned', 'scanner', 'scanner-resource', $request->capabilities);
    }

    public function release(DelegatedScannerReleaseRequest $request): DelegatedScannerPortResult
    {
        $this->record('release', $request);

        return new DelegatedScannerPortResult('released', 'scanner', $request->resourcePublicReference);
    }

    private function record(string $operation, object $request): void
    {
        $this->calls[] = compact('operation', 'request');
        if ($this->fail) {
            throw new RuntimeException('fake_scanner_failure');
        }
    }
}
