<?php

namespace Tests\Feature\Authorization;

use Database\Seeders\PermissionSeeder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('phase-4')]
#[Group('rbac')]
final class Phase4PermissionMatrixTest extends TestCase
{
    private const PHASE4_PERMISSIONS = [
        'acs.configure',
        'acs.events.view',
        'acs.health.view',
        'acs.emergency.manage',
    ];

    public function test_phase_four_permissions_exist_in_catalog(): void
    {
        $definitions = collect(PermissionSeeder::definitions())->pluck('key');

        foreach (self::PHASE4_PERMISSIONS as $permission) {
            self::assertTrue($definitions->contains($permission), "Permission {$permission} missing from catalog.");
        }
    }

    public function test_phase_four_permissions_are_tenant_scoped(): void
    {
        $definitions = collect(PermissionSeeder::definitions())->keyBy('key');

        foreach (self::PHASE4_PERMISSIONS as $permission) {
            self::assertSame('tenant', $definitions->get($permission)['scope'] ?? null, $permission);
        }
    }
}
