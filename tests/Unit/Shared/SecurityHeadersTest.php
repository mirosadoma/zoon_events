<?php

namespace Tests\Unit\Shared;

use App\Modules\Shared\Http\Middleware\SecurityHeaders;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_scanner_route_allows_camera(): void
    {
        $response = $this->runMiddleware(Request::create('/en/tenant/events/10/scanner', 'GET'));

        self::assertStringContainsString('camera=(self)', (string) $response->headers->get('Permissions-Policy'));
    }

    public function test_kiosk_route_allows_camera(): void
    {
        $response = $this->runMiddleware(Request::create('/kiosk/ABC123/scan', 'GET'));

        self::assertStringContainsString('camera=(self)', (string) $response->headers->get('Permissions-Policy'));
    }

    public function test_other_routes_block_camera(): void
    {
        $response = $this->runMiddleware(Request::create('/en/dashboard', 'GET'));

        self::assertStringContainsString('camera=()', (string) $response->headers->get('Permissions-Policy'));
        self::assertStringNotContainsString('camera=(self)', (string) $response->headers->get('Permissions-Policy'));
    }

    private function runMiddleware(Request $request): Response
    {
        $middleware = new SecurityHeaders;

        return $middleware->handle($request, fn (): Response => new Response('', 200));
    }
}
