<?php

namespace App\Modules\Shared\Http\Middleware;

use App\Modules\Shared\Domain\Context\RequestContext;
use App\Modules\Shared\Domain\Context\RequestContextStore;
use App\Modules\Shared\Domain\Locale;
use Closure;
use Illuminate\Http\Request;
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

        return $next($request);
    }

    private function resolveLocale(Request $request): Locale
    {
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
