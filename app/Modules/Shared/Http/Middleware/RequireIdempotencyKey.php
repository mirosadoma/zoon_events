<?php

namespace App\Modules\Shared\Http\Middleware;

use App\Exceptions\FoundationException;
use App\Models\User;
use App\Modules\AccessControl\Domain\Context\AcsIntegrationContextStore;
use App\Modules\Kiosk\Domain\Context\KioskSessionContextStore;
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
        private readonly AcsIntegrationContextStore $acsIntegrations,
        private readonly KioskSessionContextStore $kioskSessions,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $key = (string) $request->header('Idempotency-Key', '');
        if (strlen($key) < 16 || strlen($key) > 255) {
            throw FoundationException::validation('idempotency_key_required', 'A valid Idempotency-Key header is required.');
        }

        $actor = $request->user();
        if ($actor instanceof User) {
            $tenant = $this->tenants->currentOrNull();
            $scope = $tenant ? 'tenant' : 'platform';
            $tenantId = $tenant?->tenant->id;
            $actorId = $actor->id;
        } else {
            $kiosk = $this->kioskSessions->currentOrNull();
            if ($kiosk !== null) {
                $scope = 'tenant';
                $tenantId = $kiosk->tenantId;
                $actorId = 'kiosk:'.$kiosk->kioskId;
            } else {
                $acs = $this->acsIntegrations->currentOrNull();
                if ($acs === null) {
                    throw FoundationException::unauthenticated();
                }

                $scope = 'tenant';
                $tenantId = $acs->tenantId;
                $actorId = $acs->eventId;
            }
        }

        $operation = $request->route()?->getName() ?: $request->method().' '.$request->route()?->uri();
        $requestHash = hash('sha256', json_encode([$request->method(), $request->path(), $request->query(), $request->all()], JSON_THROW_ON_ERROR));
        $record = $this->service->acquire($scope, $tenantId, $actorId, $operation, $key, $requestHash);

        if ($record->state === 'completed') {
            $status = (int) $record->response_status;
            if ($status >= 200 && $status < 300) {
                return response()->json($record->response_body, $status, ['Idempotent-Replayed' => 'true']);
            }

            $record->delete();
            $record = $this->service->acquire($scope, $tenantId, $actorId, $operation, $key, $requestHash);
        }

        try {
            $response = $next($request);

            if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
                $body = $response instanceof JsonResponse ? $response->getData(true) : null;
                $this->service->complete($record, $response->getStatusCode(), $body);
            } else {
                $this->service->fail($record);
            }

            return $response;
        } catch (Throwable $throwable) {
            $this->service->fail($record);
            throw $throwable;
        }
    }
}
