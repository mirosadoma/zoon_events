<?php

namespace Tests\Feature\Health;

use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_public_health_is_minimal_and_readiness_checks_required_dependencies(): void
    {
        $this->getJson('/api/v1/health/live')->assertOk()->assertExactJson(['status' => 'ok']);
        $response = $this->getJson('/api/v1/health/ready');
        $response->assertOk()->assertExactJson(['status' => 'ok']);
        self::assertStringNotContainsString((string) config('app.key'), $response->getContent());
        self::assertStringNotContainsString('database', $response->getContent());
    }
}
