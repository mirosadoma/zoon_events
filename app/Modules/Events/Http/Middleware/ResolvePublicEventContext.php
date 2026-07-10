<?php

namespace App\Modules\Events\Http\Middleware;

use App\Exceptions\FoundationException;
use App\Modules\Events\Contracts\PublicEventContextResolver;
use App\Modules\Events\Domain\Context\PublicEventContextStore;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class ResolvePublicEventContext
{
    public function __construct(
        private PublicEventContextResolver $resolver,
        private PublicEventContextStore $store,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $slug = (string) $request->route('event_slug', '');
        $context = $slug === '' ? null : $this->resolver->resolve(mb_strtolower($request->getHost()), $slug);

        if ($context === null) {
            throw FoundationException::notFound();
        }

        $this->store->bind($context);

        return $next($request);
    }
}
