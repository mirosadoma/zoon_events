<?php

namespace App\Modules\Shared\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $isDev = app()->environment('local');

        $scriptSrc = ["'self'"];
        $styleSrc = ["'self'", "'unsafe-inline'"];
        $connectSrc = ["'self'"];
        $imgSrc = ["'self'", 'data:', 'blob:', 'https://*.tile.openstreetmap.org'];

        if ($isDev) {
            $scriptSrc[] = "'unsafe-inline'"; // مهم لـ Vite + React Refresh
            $scriptSrc[] = 'http://127.0.0.1:5173';

            $styleSrc[] = 'http://127.0.0.1:5173';
            $imgSrc[] = 'http://127.0.0.1:5173';

            $connectSrc[] = 'http://127.0.0.1:5173';
            $connectSrc[] = 'ws://127.0.0.1:5173';
        }

        $csp = sprintf(
            "default-src 'self'; img-src %s; style-src %s; script-src %s; connect-src %s; frame-ancestors 'none'; base-uri 'self'; form-action 'self'",
            implode(' ', $imgSrc),
            implode(' ', $styleSrc),
            implode(' ', $scriptSrc),
            implode(' ', $connectSrc),
        );

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('Content-Security-Policy', $csp);

        return $response;
    }
}
