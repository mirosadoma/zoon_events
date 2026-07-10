<?php

namespace App\Modules\Shared\Http\Middleware;

use App\Modules\Shared\Domain\Context\CorrelationId;
use App\Modules\Shared\Domain\Context\RequestContext;
use App\Modules\Shared\Domain\Context\RequestContextStore;
use App\Modules\Shared\Domain\Context\RequestId;
use App\Modules\Shared\Domain\Locale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AssignRequestContext
{
    public function __construct(
        private readonly RequestContextStore $store,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $context = new RequestContext(
            CorrelationId::fromHeader($request->headers->get('X-Correlation-ID')),
            RequestId::generate(),
            Locale::default(),
        );

        $this->store->set($context);

        try {
            /** @var Response $response */
            $response = $next($request);
        } finally {
            $effective = $this->store->current() ?? $context;
            $response ??= response()->noContent();
            $response->headers->set('X-Correlation-ID', $effective->correlationId->value);
            $this->store->clear();
        }

        return $response;
    }
}
