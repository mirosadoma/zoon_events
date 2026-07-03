<?php

namespace App\Modules\Operations\Application\Telemetry;

use App\Modules\Shared\Domain\Context\RequestContextStore;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class RecordRequestTelemetry
{
    public function __construct(
        private readonly TelemetryPipeline $telemetry,
        private readonly RequestContextStore $requestContexts,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $started = microtime(true);
        $status = 500;

        try {
            $response = $next($request);
            $status = $response->getStatusCode();

            return $response;
        } catch (Throwable $throwable) {
            $status = method_exists($throwable, 'getStatusCode')
                ? (int) $throwable->getStatusCode()
                : ((int) $throwable->getCode() >= 400 && (int) $throwable->getCode() <= 599 ? (int) $throwable->getCode() : 500);

            throw $throwable;
        } finally {
            $context = $this->requestContexts->current();
            $this->telemetry->emit('http.request.completed', [
                'request_id' => $context?->requestId->value,
                'correlation_id' => $context?->correlationId->value,
                'tenant_id' => $request->attributes->get('trusted_tenant_id'),
                'actor_id' => $request->user()?->id,
                'method' => $request->method(),
                'route' => $request->route()?->getName() ?? $request->route()?->uri(),
                'status' => $status,
                'duration_ms' => (int) ((microtime(true) - $started) * 1000),
            ]);
        }
    }
}
