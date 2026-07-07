<?php

namespace App\Modules\AccessControl\Http\Middleware;

use App\Modules\AccessControl\Domain\Context\AcsIntegrationContextStore;
use App\Modules\AccessControl\Domain\ValueObjects\AcsIntegrationContext;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsIntegrationCredential;
use App\Modules\Shared\Http\Problems\Phase4Problem;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ResolveAcsIntegration
{
    public function __construct(
        private readonly AcsIntegrationContextStore $store,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('Authorization', '');

        if (! str_starts_with($header, 'AcsIntegration ')) {
            throw Phase4Problem::make('acs_integration_invalid');
        }

        $rawSecret = substr($header, strlen('AcsIntegration '));

        if ($rawSecret === '' || $rawSecret === false) {
            throw Phase4Problem::make('acs_integration_invalid');
        }

        $credential = AcsIntegrationCredential::query()
            ->where('secret_hash', hash('sha256', $rawSecret))
            ->where('status', 'active')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->first();

        if ($credential === null) {
            throw Phase4Problem::make('acs_integration_invalid');
        }

        $this->store->bind(new AcsIntegrationContext(
            tenantId: $credential->tenant_id,
            eventId: $credential->event_id,
            capabilities: (array) $credential->capabilities,
        ));

        return $next($request);
    }
}
