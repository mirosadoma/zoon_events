<?php

namespace App\Modules\Kiosk\Contracts;

interface KioskFleetSummaryProvider
{
    /**
     * @return array<string, int> counts keyed by derived status
     *                            (online, offline, degraded, retired, pending)
     */
    public function summarize(): array;
}
