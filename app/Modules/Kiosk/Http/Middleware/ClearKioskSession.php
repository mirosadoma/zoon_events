<?php

namespace App\Modules\Kiosk\Http\Middleware;

use App\Modules\Kiosk\Domain\Context\KioskSessionContextStore;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ClearKioskSession
{
    public function __construct(
        private readonly KioskSessionContextStore $store,
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
