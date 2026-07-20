<?php

namespace App\Modules\Authorization\Application\Services;

use App\Modules\Authorization\Application\Contracts\DelegatedControlDecision;
use App\Modules\Authorization\Application\Contracts\DelegatedControlGuard;
use App\Modules\Authorization\Application\Contracts\DelegatedControlRequest;

final class DenyingDelegatedControlGuard implements DelegatedControlGuard
{
    public function decide(DelegatedControlRequest $request): DelegatedControlDecision
    {
        if (! $request->existingPermissionAllowed) {
            return new DelegatedControlDecision(false, 'marketplace_permission_denied');
        }

        if ($request->delegationPublicId === null) {
            return new DelegatedControlDecision(true);
        }

        return new DelegatedControlDecision(false, 'marketplace_delegation_not_found');
    }
}
