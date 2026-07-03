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
    }
}
