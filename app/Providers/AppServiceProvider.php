<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('tenant', fn (Request $request): Limit => Limit::perMinute(120)->by(($request->user()?->id ?? 'guest').'|'.($request->header('X-Tenant-ID') ?? 'none')));
        RateLimiter::for('platform', fn (Request $request): Limit => Limit::perMinute(60)->by($request->user()?->id ?? $request->ip()));
        RateLimiter::for('privileged-export', fn (Request $request): Limit => Limit::perMinute(5)->by(($request->user()?->id ?? 'guest').'|'.($request->header('X-Tenant-ID') ?? 'platform')));
        RateLimiter::for('public-event', fn (Request $request): Limit => Limit::perMinute(120)
            ->by($this->publicKey($request, 'event')));
        RateLimiter::for('public-registration', fn (Request $request): Limit => Limit::perMinute(20)
            ->by($this->publicKey($request, 'registration')));
        RateLimiter::for('public-checkout', fn (Request $request): Limit => Limit::perMinute(10)
            ->by($this->publicKey($request, 'checkout')));
        RateLimiter::for('payment-callback', fn (Request $request): Limit => Limit::perMinute(300)
            ->by(hash('sha256', 'callback|'.mb_strtolower($request->getHost()).'|'.$request->route('route_token', 'none'))));
        RateLimiter::for('notification-callback', fn (Request $request): Limit => Limit::perMinute(300)
            ->by(hash('sha256', 'notification|'.mb_strtolower($request->getHost()).'|'.$request->route('route_token', 'none'))));
        RateLimiter::for('phase1-organizer', fn (Request $request): Limit => Limit::perMinute(90)
            ->by(($request->user()?->id ?? 'guest').'|'.($request->header('X-Tenant-ID') ?? 'none')));
    }

    private function publicKey(Request $request, string $operation): string
    {
        return hash('sha256', implode('|', [
            $operation,
            mb_strtolower($request->getHost()),
            (string) $request->route('event_slug', 'none'),
            (string) $request->ip(),
        ]));
    }
}
