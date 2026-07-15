<?php

namespace App\Modules\AccessControl\Application\Contracts;

interface DelegatedAcsAssetPort
{
    public function provision(DelegatedAcsProvisionRequest $request): DelegatedAcsPortResult;

    public function release(DelegatedAcsReleaseRequest $request): DelegatedAcsPortResult;
}
