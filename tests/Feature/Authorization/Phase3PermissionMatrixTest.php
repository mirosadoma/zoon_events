<?php

namespace Tests\Feature\Authorization;

use Database\Seeders\PermissionSeeder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('phase-3')]
#[Group('rbac')]
final class Phase3PermissionMatrixTest extends TestCase
{
    private const PHASE3_PERMISSIONS = [
        'kiosk.manage',
        'kiosk.health.view',
        'checkin.desk.perform',
        'badge.print',
        'badge.reprint',
        'badge.template.manage',
        'attendee.walkup.register',
    ];

    public function test_phase_three_permissions_do_not_yet_exist_before_seeding(): void
    {
        $definitions = collect(PermissionSeeder::definitions())->pluck('key');

        foreach (self::PHASE3_PERMISSIONS as $permission) {
            self::assertTrue($definitions->contains($permission), "Permission {$permission} missing from catalog.");
        }
    }

    public function test_phase_three_permissions_are_tenant_scoped(): void
    {
        $definitions = collect(PermissionSeeder::definitions())->keyBy('key');

        foreach (self::PHASE3_PERMISSIONS as $permission) {
            self::assertSame('tenant', $definitions->get($permission)['scope'] ?? null, $permission);
        }
    }
}
