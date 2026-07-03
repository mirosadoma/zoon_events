<?php

namespace Tests\Feature\Shared;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ApiRouteGroupingTest extends TestCase
{
    #[Test]
    public function platform_and_tenant_route_groups_are_distinct(): void
    {
        $platform = $this->getJson('/api/v1/platform/tenants');
        $tenant = $this->getJson('/api/v1/tenant/memberships');

        $platform->assertUnauthorized();
        $tenant->assertUnauthorized();
        $this->getJson('/api/v1/platform/_placeholder')->assertNotFound();
        $this->getJson('/api/v1/tenant/_placeholder')->assertNotFound();
    }
}
