<?php

namespace App\Modules\Shared\Http\Middleware;

use App\Modules\Shared\Support\LocaleDetector;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RedirectToLocalizedUrl
{
    /** @var list<string> */
    private const EXCLUDED_PREFIXES = [
        'api',
        'build',
        'landing',
        'identity',
        'kiosk',
        'health',
        'sanctum',
        '_ignition',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $path = trim($request->path(), '/');
        $first = explode('/', $path)[0] ?? '';

        if (in_array($first, ['en', 'ar'], true)) {
            return $next($request);
        }

        foreach (self::EXCLUDED_PREFIXES as $prefix) {
            if ($path === $prefix || str_starts_with($path, "{$prefix}/")) {
                return $next($request);
            }
        }

        if ($path === '') {
            return redirect('/'.LocaleDetector::detect($request));
        }

        return redirect('/'.LocaleDetector::detect($request).'/'.$path);
    }
}
