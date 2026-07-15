<?php

namespace App\Modules\Kiosk\Application\Contracts;

interface DelegatedKioskAssetPort
{
    public function provision(DelegatedKioskProvisionRequest $request): DelegatedKioskPortResult;

    public function release(DelegatedKioskReleaseRequest $request): DelegatedKioskPortResult;
}
