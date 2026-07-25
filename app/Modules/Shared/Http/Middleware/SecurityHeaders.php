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
        $isEmbeddable = $this->isEmbeddableRoute($request);

        $scriptSrc = ["'self'", 'https://maps.googleapis.com', 'https://maps.gstatic.com'];
        $styleSrc = ["'self'", "'unsafe-inline'", 'https://fonts.googleapis.com'];
        $connectSrc = ["'self'", 'https://maps.googleapis.com', 'https://maps.gstatic.com', 'https://*.googleapis.com'];
        $imgSrc = [
            "'self'",
            'data:',
            'blob:',
            'https://*.tile.openstreetmap.org',
            'https://maps.gstatic.com',
            'https://maps.googleapis.com',
            'https://*.googleapis.com',
            'https://*.ggpht.com',
        ];
        $fontSrc = ["'self'", 'https://fonts.gstatic.com'];
        $workerSrc = ["'self'", 'blob:'];

        if ($isDev) {
            $scriptSrc[] = "'unsafe-inline'";
            $scriptSrc[] = 'http://127.0.0.1:5173';

            $styleSrc[] = 'http://127.0.0.1:5173';
            $imgSrc[] = 'http://127.0.0.1:5173';

            $connectSrc[] = 'http://127.0.0.1:5173';
            $connectSrc[] = 'ws://127.0.0.1:5173';
        }

        $frameAncestors = $isEmbeddable ? '*' : "'none'";

        $csp = sprintf(
            "default-src 'self'; img-src %s; style-src %s; script-src %s; connect-src %s; font-src %s; worker-src %s; frame-ancestors %s; base-uri 'self'; form-action 'self'",
            implode(' ', $imgSrc),
            implode(' ', $styleSrc),
            implode(' ', $scriptSrc),
            implode(' ', $connectSrc),
            implode(' ', $fontSrc),
            implode(' ', $workerSrc),
            $frameAncestors,
        );

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', $isEmbeddable ? 'ALLOWALL' : 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('Content-Security-Policy', $csp);

        return $response;
    }

    private function isEmbeddableRoute(Request $request): bool
    {
        $path = $request->path();

        return str_contains($path, '/register/') || preg_match('#^(en|ar)/register/#', $path);
    }
}
