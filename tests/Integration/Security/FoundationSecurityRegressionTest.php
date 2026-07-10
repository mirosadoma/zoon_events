<?php

namespace Tests\Integration\Security;

use Tests\TestCase;

class FoundationSecurityRegressionTest extends TestCase
{
    public function test_errors_and_responses_do_not_leak_internals_and_include_security_headers(): void
    {
        $response = $this->getJson('/api/v1/does-not-exist');
        $response->assertNotFound()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Content-Security-Policy');
        $content = $response->getContent();
        self::assertStringNotContainsString(base_path(), $content);
        self::assertStringNotContainsString('stack', mb_strtolower($content));
        self::assertStringNotContainsString((string) config('app.key'), $content);
    }

    public function test_common_injection_payloads_are_rejected_as_validation_or_not_found(): void
    {
        $this->getJson('/api/v1/platform/tenants/%27%20OR%201=1--')->assertUnauthorized();
        $this->postJson('/api/v1/auth/token', ['email' => '<script>alert(1)</script>', 'password' => 'x', 'device_name' => 'x'])->assertUnprocessable();
    }
}
