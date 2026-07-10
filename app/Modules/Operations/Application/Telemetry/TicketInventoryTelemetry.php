<?php

namespace App\Modules\Operations\Application\Telemetry;

use App\Modules\Operations\Contracts\Telemetry\TelemetryExporter;

final readonly class TicketInventoryTelemetry
{
    public function __construct(private TelemetryExporter $telemetry) {}

    public function transition(string $transition, int $lockDurationMs): void
    {
        $this->telemetry->metric('ticket_inventory_transition', 1, ['transition' => $transition]);
        $this->telemetry->metric('ticket_inventory_lock_duration_ms', $lockDurationMs);
        if ($lockDurationMs >= 250) {
            $this->telemetry->log('ticket_inventory_slow_lock', ['duration_bucket' => '250ms_or_more']);
        }
    }
}
