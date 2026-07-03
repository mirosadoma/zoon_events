<?php

namespace App\Modules\AdminConsole\Http\Middleware;

use App\Exceptions\FoundationException;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

final class AuthorizeDashboardPage
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (Gate::forUser($request->user())->denies($permission)) {
            throw FoundationException::forbidden();
        }

        return $next($request);
    }
}
