<?php

namespace App\Modules\AccessControl\Contracts;

use App\Modules\AccessControl\Domain\Results\AcsHealthResult;

interface AcsAdapter
{
    public function health(): AcsHealthResult;

    /**
     * Whether the ACS dependency is currently reachable within the decision
     * latency budget. Gate authorization uses this (not the concrete adapter
     * type) to apply each zone's fail-open/fail-closed unavailability mode.
     */
    public function isAvailable(): bool;
}
