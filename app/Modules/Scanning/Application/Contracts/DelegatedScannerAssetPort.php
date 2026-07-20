<?php

namespace App\Modules\Scanning\Application\Contracts;

interface DelegatedScannerAssetPort
{
    public function provision(DelegatedScannerProvisionRequest $request): DelegatedScannerPortResult;

    public function release(DelegatedScannerReleaseRequest $request): DelegatedScannerPortResult;
}
