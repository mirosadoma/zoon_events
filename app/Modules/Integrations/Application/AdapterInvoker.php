<?php

namespace App\Modules\Integrations\Application;

use App\Modules\Integrations\Domain\AdapterResult;
use App\Modules\Integrations\Domain\AdapterRetryPolicy;
use App\Modules\Operations\Application\Telemetry\TelemetryPipeline;

final class AdapterInvoker
{
    public function __construct(
        private readonly AdapterRegistry $registry,
        private readonly ValidateAdapterContext $validator,
        private readonly TelemetryPipeline $telemetry,
    ) {}

    public function invoke(string $capability, AdapterInvocationContext $context, array $request, ?string $adapterKey = null): AdapterResult
    {
        $this->validator->validate($context);
        $adapter = $this->registry->for($capability, $adapterKey);
        $attempt = 0;

        do {
            $attempt++;
            $started = microtime(true);
            $result = $adapter->execute($context, $request);
            $this->telemetry->emit('adapter.invoked', [
                'tenant_id' => $context->tenantId,
                'correlation_id' => $context->correlationId,
                'capability' => $capability,
                'adapter' => $adapter->descriptor()->key,
                'status' => $result->status->value,
                'attempt' => $attempt,
                'duration_ms' => (int) ((microtime(true) - $started) * 1000),
            ]);

            if ($result->retryPolicy !== AdapterRetryPolicy::Safe || $attempt >= 3) {
                return $result;
            }

            usleep(1000 * (10 * $attempt));
        } while (true);
    }
}
