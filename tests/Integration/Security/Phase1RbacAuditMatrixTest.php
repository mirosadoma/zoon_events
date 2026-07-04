<?php

namespace Tests\Integration\Security;

use App\Modules\Audit\Application\Listeners\Phase1\Phase1AuditMapping;
use Database\Seeders\PermissionSeeder;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('phase-1')]
#[Group('phase-1-rbac')]
#[Group('phase-1-audit')]
final class Phase1RbacAuditMatrixTest extends TestCase
{
    public function test_every_phase_one_route_permission_exists_in_the_catalog(): void
    {
        $catalog = array_column(PermissionSeeder::definitions(), 'key');
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($route): bool => str_starts_with($route->uri(), 'api/v1/tenant/events/'));
        foreach ($routes as $route) {
            foreach ($route->gatherMiddleware() as $middleware) {
                if (preg_match('/^permission:([^,]+)/', $middleware, $match)) {
                    self::assertContains($match[1], $catalog, $route->uri());
                }
            }
        }
    }

    public function test_phase_one_audit_mapping_rejects_unregistered_events(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(Phase1AuditMapping::class)->for(new \stdClass);
    }
}
