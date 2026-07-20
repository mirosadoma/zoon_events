<?php

namespace App\Modules\VenueMarketplace\Testing\Fakes;

use App\Modules\Authorization\Application\Contracts\DelegatedControlDecision;
use App\Modules\Authorization\Application\Contracts\DelegatedControlGuard;
use App\Modules\Authorization\Application\Contracts\DelegatedControlRequest;

final class FakeDelegatedControlGuard implements DelegatedControlGuard
{
    public array $calls = [];

    public bool $allow = true;

    public string $denialReason = 'marketplace_delegation_degraded';

    public function decide(DelegatedControlRequest $request): DelegatedControlDecision
    {
        $this->calls[] = $request;

        return new DelegatedControlDecision($this->allow, $this->allow ? null : $this->denialReason);
    }
}
