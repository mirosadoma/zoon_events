<?php

namespace App\Modules\Scanning\Http\Middleware;

use App\Modules\Scanning\Domain\Context\ScannerAppSessionContextStore;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ClearScannerAppSession
{
    public function __construct(
        private readonly ScannerAppSessionContextStore $store,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->store->clear();

        try {
            return $next($request);
        } finally {
            $this->store->clear();
        }
    }
}
