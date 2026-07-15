<?php

namespace App\Modules\Authorization\Application\Contracts;

interface DelegatedControlGuard
{
    public function decide(DelegatedControlRequest $request): DelegatedControlDecision;
}
