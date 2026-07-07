<?php

namespace App\Modules\AccessControl\Http\Middleware;

use App\Modules\AccessControl\Domain\Context\AcsIntegrationContextStore;
use App\Modules\Shared\Http\Problems\Phase4Problem;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireAcsCapability
{
    public function __construct(
        private readonly AcsIntegrationContextStore $store,
    ) {}

    public function handle(Request $request, Closure $next, string $capability): Response
    {
        if (! in_array($capability, $this->store->current()->capabilities, true)) {
            throw Phase4Problem::make('acs_capability_denied');
        }

        return $next($request);
    }
}
