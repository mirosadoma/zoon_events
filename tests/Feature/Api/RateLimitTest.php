<?php

namespace Tests\Feature\Api;

use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_authentication_limiter_is_bounded_and_returns_safe_problem_details(): void
    {
        $request = Request::create('/api/v1/auth/token', 'POST', [
            'email' => 'rate-limit-probe@example.test',
        ]);
        $limit = app(RateLimiter::class)->limiter('auth')($request);

        self::assertInstanceOf(Limit::class, $limit);
        self::assertSame(5, $limit->maxAttempts);

        /** @var Response $response */
        $response = ($limit->responseCallback)($request, ['Retry-After' => '60']);
        self::assertSame(429, $response->getStatusCode());
        self::assertSame('60', $response->headers->get('Retry-After'));
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));
        self::assertSame('rate_limited', json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR)['code']);
        self::assertStringNotContainsString('rate-limit-probe', $response->getContent());
    }

    public function test_platform_tenant_and_export_routes_have_their_named_limiters(): void
    {
        $middlewareFor = static function (string $uri, string $method): array {
            foreach (Route::getRoutes() as $route) {
                if ($route->uri() === $uri && in_array($method, $route->methods(), true)) {
                    return $route->gatherMiddleware();
                }
            }

            self::fail("Route {$method} {$uri} was not registered.");
        };

        self::assertContains('throttle:platform', $middlewareFor('api/v1/platform/tenants', 'GET'));
        self::assertContains('throttle:tenant', $middlewareFor('api/v1/tenant/memberships', 'GET'));
        self::assertContains('throttle:privileged-export', $middlewareFor('api/v1/tenant/audit-exports', 'POST'));
    }
}
