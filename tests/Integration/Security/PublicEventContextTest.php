<?php

namespace Tests\Integration\Security;

use App\Exceptions\FoundationException;
use App\Modules\Events\Contracts\PublicEventContextResolver;
use App\Modules\Events\Domain\Context\PublicEventContext;
use App\Modules\Events\Domain\Context\PublicEventContextStore;
use App\Modules\Events\Http\Middleware\ClearPublicEventContext;
use App\Modules\Events\Http\Middleware\ResolvePublicEventContext;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

#[Group('phase-1')]
final class PublicEventContextTest extends TestCase
{
    public function test_resolver_uses_host_and_slug_and_ignores_forged_tenant_header(): void
    {
        $context = new PublicEventContext('tenant-trusted', 'event-trusted', 'events.example', 'summit');
        $resolver = new class($context) implements PublicEventContextResolver
        {
            public array $received = [];

            public function __construct(private readonly PublicEventContext $context) {}

            public function resolve(string $host, string $eventSlug): ?PublicEventContext
            {
                $this->received = [$host, $eventSlug];

                return $this->context;
            }
        };
        $store = new PublicEventContextStore;
        $request = Request::create('https://events.example/api/v1/public/events/summit', server: ['HTTP_X_TENANT_ID' => 'tenant-forged']);
        $request->setRouteResolver(fn () => new class
        {
            public function parameter(string $key, mixed $default = null): mixed
            {
                return $key === 'event_slug' ? 'summit' : $default;
            }
        });

        $response = (new ClearPublicEventContext($store))->handle(
            $request,
            fn (Request $request): Response => (new ResolvePublicEventContext($resolver, $store))->handle(
                $request,
                function () use ($store): Response {
                    self::assertSame('tenant-trusted', $store->current()->tenantId);

                    return new Response('ok');
                },
            ),
        );

        self::assertSame('ok', $response->getContent());
        self::assertSame(['events.example', 'summit'], $resolver->received);
        self::assertNull($store->currentOrNull());
    }

    public function test_unknown_or_inactive_resolution_fails_closed(): void
    {
        $resolver = new class implements PublicEventContextResolver
        {
            public function resolve(string $host, string $eventSlug): ?PublicEventContext
            {
                return null;
            }
        };
        $store = new PublicEventContextStore;
        $request = Request::create('https://unknown.example/api/v1/public/events/missing');
        $request->setRouteResolver(fn () => new class
        {
            public function parameter(string $key, mixed $default = null): mixed
            {
                return $key === 'event_slug' ? 'missing' : $default;
            }
        });

        $this->expectException(FoundationException::class);
        (new ResolvePublicEventContext($resolver, $store))->handle($request, fn (): Response => new Response);
    }
}
