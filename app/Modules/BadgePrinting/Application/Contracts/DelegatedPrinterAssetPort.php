<?php

namespace App\Modules\BadgePrinting\Application\Contracts;

interface DelegatedPrinterAssetPort
{
    public function provision(DelegatedPrinterProvisionRequest $request): DelegatedPrinterPortResult;

    public function release(DelegatedPrinterReleaseRequest $request): DelegatedPrinterPortResult;
}
