<?php

namespace App\Modules\Shared\Http\Middleware;

use App\Exceptions\FoundationException;
use App\Models\User;
use App\Modules\Shared\Application\Idempotency\IdempotencyService;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class RequireIdempotencyKey
{
    public function __construct(
        private readonly IdempotencyService $service,
        private readonly TenantContextStore $tenants,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $key = (string) $request->header('Idempotency-Key', '');
        if (strlen($key) < 16 || strlen($key) > 255) {
            throw FoundationException::validation('idempotency_key_required', 'A valid Idempotency-Key header is required.');
        }

        $actor = $request->user();
        if (! $actor instanceof User) {
            throw FoundationException::unauthenticated();
        }

        $tenant = $this->tenants->currentOrNull();
        $scope = $tenant ? 'tenant' : 'platform';
        $tenantId = $tenant?->tenant->id;
        $operation = $request->route()?->getName() ?: $request->method().' '.$request->route()?->uri();
        $requestHash = hash('sha256', json_encode([$request->method(), $request->path(), $request->query(), $request->all()], JSON_THROW_ON_ERROR));
        $record = $this->service->acquire($scope, $tenantId, $actor->id, $operation, $key, $requestHash);

        if ($record->state === 'completed') {
            return response()->json($record->response_body, $record->response_status, ['Idempotent-Replayed' => 'true']);
        }

        try {
            $response = $next($request);
            $body = $response instanceof JsonResponse ? $response->getData(true) : null;
            $this->service->complete($record, $response->getStatusCode(), $body);

            return $response;
        } catch (Throwable $throwable) {
            $this->service->fail($record);
            throw $throwable;
        }
    }
}
