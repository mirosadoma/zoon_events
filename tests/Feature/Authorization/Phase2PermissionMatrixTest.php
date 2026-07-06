<?php

namespace Tests\Feature\Authorization;

use Database\Seeders\PermissionSeeder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('phase-2')]
#[Group('rbac')]
final class Phase2PermissionMatrixTest extends TestCase
{
    public function test_phase_two_permission_catalog_is_complete_and_tenant_scoped(): void
    {
        $expected = [
            'wallet.pass.view',
            'wallet.pass.generate',
            'wallet.pass.manage',
            'checkin.scan.submit',
            'checkin.scan.override',
            'checkin.dashboard.view',
        ];
        $definitions = collect(PermissionSeeder::definitions())->keyBy('key');

        foreach ($expected as $permission) {
            self::assertSame('tenant', $definitions->get($permission)['scope'] ?? null, $permission);
        }
    }
}
