<?php

namespace App\Modules\Events\Http\Middleware;

use App\Modules\Events\Domain\Context\PublicEventContextStore;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class ClearPublicEventContext
{
    public function __construct(private PublicEventContextStore $store) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            return $next($request);
        } finally {
            $this->store->clear();
        }
    }
}
