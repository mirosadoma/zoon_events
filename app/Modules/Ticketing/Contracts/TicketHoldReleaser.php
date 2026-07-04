<?php

namespace App\Modules\Ticketing\Contracts;

interface TicketHoldReleaser
{
    public function release(string $tenantId, string $holdId, string $reason = 'released'): object;
}
