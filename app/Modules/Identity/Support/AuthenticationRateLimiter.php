<?php

namespace App\Modules\Identity\Support;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

final class AuthenticationRateLimiter
{
    public static function register(): void
    {
        RateLimiter::for('auth', fn (Request $request): Limit => Limit::perMinute(5)
            ->by(hash('sha256', mb_strtolower((string) $request->input('email')).'|'.$request->ip()))
            ->response(fn () => response()->json([
                'type' => 'https://docs.zonetec.example/problems/rate_limited',
                'title' => 'Rate limited',
                'status' => 429,
                'code' => 'rate_limited',
                'detail' => 'Too many authentication attempts.',
            ], 429, ['Content-Type' => 'application/problem+json', 'Retry-After' => '60'])));
    }
}
