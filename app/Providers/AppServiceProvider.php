<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
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
        // Root-relative asset URLs so Vite's CSS preload helper can match existing
        // <link> tags (absolute APP_URL hrefs cause duplicate crossorigin loads → crash).
        Vite::createAssetPathsUsing(
            fn (string $path, ?bool $secure = null): string => '/'.ltrim($path, '/'),
        );

        $this->app->booted(function (): void {
            $request = request();
            $locale = 'en';

            if ($request instanceof Request) {
                $routeLocale = $request->route('locale');

                if (is_string($routeLocale) && in_array($routeLocale, ['en', 'ar'], true)) {
                    $locale = $routeLocale;
                } elseif (is_string($request->cookie('locale'))) {
                    $locale = substr($request->cookie('locale'), 0, 2);
                } elseif ($request->user()?->preferred_locale) {
                    $locale = $request->user()->preferred_locale;
                }
            }

            URL::defaults(['locale' => in_array($locale, ['en', 'ar'], true) ? $locale : 'en']);
        });

        RateLimiter::for('tenant', fn (Request $request): Limit => Limit::perMinute(120)->by(($request->user()?->id ?? 'guest').'|'.($request->header('X-Tenant-ID') ?? 'none')));
        RateLimiter::for('platform', fn (Request $request): Limit => Limit::perMinute(60)->by($request->user()?->id ?? $request->ip()));
        RateLimiter::for('privileged-export', fn (Request $request): Limit => Limit::perMinute(5)->by(($request->user()?->id ?? 'guest').'|'.($request->header('X-Tenant-ID') ?? 'platform')));
        RateLimiter::for('public-event', fn (Request $request): Limit => Limit::perMinute(120)
            ->by($this->publicKey($request, 'event')));
        RateLimiter::for('public-registration', fn (Request $request): Limit => Limit::perMinute(20)
            ->by($this->publicKey($request, 'registration')));
        RateLimiter::for('public-checkout', fn (Request $request): Limit => Limit::perMinute(10)
            ->by($this->publicKey($request, 'checkout')));
        RateLimiter::for('public-wallet', fn (Request $request): Limit => Limit::perMinute(20)
            ->by($this->publicKey($request, 'wallet')));
        RateLimiter::for('payment-callback', fn (Request $request): Limit => Limit::perMinute(300)
            ->by(hash('sha256', 'callback|'.mb_strtolower($request->getHost()).'|'.$request->route('route_token', 'none'))));
        RateLimiter::for('notification-callback', fn (Request $request): Limit => Limit::perMinute(300)
            ->by(hash('sha256', 'notification|'.mb_strtolower($request->getHost()).'|'.$request->route('route_token', 'none'))));
        RateLimiter::for('phase1-organizer', fn (Request $request): Limit => Limit::perMinute(90)
            ->by(($request->user()?->id ?? 'guest').'|'.($request->header('X-Tenant-ID') ?? 'none')));
        RateLimiter::for('apple-wallet-webservice', fn (Request $request): Limit => Limit::perMinute(120)
            ->by(hash('sha256', 'apple-wallet|'.($request->header('Authorization') ?? $request->ip()))));
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
