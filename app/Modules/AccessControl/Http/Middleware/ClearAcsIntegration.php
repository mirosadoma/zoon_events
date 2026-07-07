<?php

namespace App\Modules\AccessControl\Http\Middleware;

use App\Modules\AccessControl\Domain\Context\AcsIntegrationContextStore;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ClearAcsIntegration
{
    public function __construct(
        private readonly AcsIntegrationContextStore $store,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            /** @var Response $response */
            $response = $next($request);
        } finally {
            $this->store->clear();
        }

        return $response;
    }
}
