<?php

namespace App\Modules\VenueMarketplace\Testing\Fakes;

use App\Modules\BadgePrinting\Application\Contracts\DelegatedPrinterAssetPort;
use App\Modules\BadgePrinting\Application\Contracts\DelegatedPrinterPortResult;
use App\Modules\BadgePrinting\Application\Contracts\DelegatedPrinterProvisionRequest;
use App\Modules\BadgePrinting\Application\Contracts\DelegatedPrinterReleaseRequest;
use RuntimeException;

final class FakeDelegatedPrinterAssetPort implements DelegatedPrinterAssetPort
{
    public array $calls = [];

    public bool $fail = false;

    public function provision(DelegatedPrinterProvisionRequest $request): DelegatedPrinterPortResult
    {
        $this->record('provision', $request);

        return new DelegatedPrinterPortResult('provisioned', 'printer', 'printer-resource', $request->capabilities);
    }

    public function release(DelegatedPrinterReleaseRequest $request): DelegatedPrinterPortResult
    {
        $this->record('release', $request);

        return new DelegatedPrinterPortResult('released', 'printer', $request->resourcePublicReference);
    }

    private function record(string $operation, object $request): void
    {
        $this->calls[] = compact('operation', 'request');
        if ($this->fail) {
            throw new RuntimeException('fake_printer_failure');
        }
    }
}
