<?php

namespace App\Modules\Operations\Application\Telemetry;

use App\Modules\Operations\Contracts\Telemetry\TelemetryExporter;

final readonly class NotificationTelemetry
{
    public function __construct(private TelemetryExporter $telemetry) {}

    public function record(string $channel, string $status): void
    {
        $this->telemetry->metric('phase1.notification.delivery', 1, [
            'channel' => $channel,
            'status' => $status,
        ]);
    }
}
