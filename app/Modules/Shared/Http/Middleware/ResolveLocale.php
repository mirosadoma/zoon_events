<?php

namespace App\Modules\Shared\Http\Middleware;

use App\Modules\Shared\Domain\Context\RequestContext;
use App\Modules\Shared\Domain\Context\RequestContextStore;
use App\Modules\Shared\Domain\Locale;
use App\Modules\Shared\Support\LocaleDetector;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

class ResolveLocale
{
    public function __construct(
        private readonly RequestContextStore $store,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolveLocale($request);

        app()->setLocale($locale->value);

        if ($context = $this->store->current()) {
            $this->store->set(new RequestContext(
                $context->correlationId,
                $context->requestId,
                $locale,
            ));
        }

        Cookie::queue('locale', $locale->value, 60 * 24 * 365);

        return $next($request);
    }

    private function resolveLocale(Request $request): Locale
    {
        $routeLocale = $request->route('locale');

        if (is_string($routeLocale)) {
            $normalized = strtolower(substr($routeLocale, 0, 2));

            if ($normalized === Locale::Arabic->value) {
                return Locale::Arabic;
            }

            if ($normalized === Locale::English->value) {
                return Locale::English;
            }
        }

        $pathLocale = LocaleDetector::fromPath($request);

        if ($pathLocale === Locale::Arabic->value) {
            return Locale::Arabic;
        }

        if ($pathLocale === Locale::English->value) {
            return Locale::English;
        }

        $cookie = $request->cookie('locale');

        if (is_string($cookie)) {
            $normalized = strtolower(substr($cookie, 0, 2));

            if ($normalized === Locale::Arabic->value) {
                return Locale::Arabic;
            }

            if ($normalized === Locale::English->value) {
                return Locale::English;
            }
        }

        $user = $request->user();

        if ($user !== null && in_array($user->preferred_locale, [Locale::Arabic->value, Locale::English->value], true)) {
            return $user->preferred_locale === Locale::Arabic->value ? Locale::Arabic : Locale::English;
        }

        $accepted = $request->getLanguages();

        foreach ($accepted as $candidate) {
            $normalized = strtolower(substr($candidate, 0, 2));

            if ($normalized === Locale::Arabic->value) {
                return Locale::Arabic;
            }

            if ($normalized === Locale::English->value) {
                return Locale::English;
            }
        }

        return Locale::default();
    }
}
