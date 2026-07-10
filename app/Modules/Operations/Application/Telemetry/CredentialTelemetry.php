<?php

namespace App\Modules\Operations\Application\Telemetry;

use App\Modules\Operations\Contracts\Telemetry\TelemetryExporter;

final readonly class CredentialTelemetry
{
    public function __construct(private TelemetryExporter $telemetry) {}

    public function outcome(string $outcome): void
    {
        $this->telemetry->metric('credential_validation_outcome', 1, ['outcome' => $outcome]);
    }
}
