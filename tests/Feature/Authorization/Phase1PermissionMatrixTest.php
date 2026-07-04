<?php

namespace Tests\Feature\Authorization;

use App\Modules\Authorization\Policies\Phase1\Phase1Policy;
use Database\Seeders\PermissionSeeder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('phase-1')]
#[Group('rbac')]
final class Phase1PermissionMatrixTest extends TestCase
{
    public function test_phase_one_permission_catalog_is_complete_and_tenant_scoped(): void
    {
        $expected = [
            'event.view', 'event.manage', 'event.publish', 'event.cancel', 'event.reopen', 'event.archive',
            'registration.manage', 'ticketing.manage', 'order.view', 'order.manage',
            'payment.refund', 'attendee.view', 'attendee.manage', 'credential.view',
            'credential.validate', 'credential.revoke', 'credential.reissue',
        ];
        $definitions = collect(PermissionSeeder::definitions())->keyBy('key');

        foreach ($expected as $permission) {
            self::assertSame('tenant', $definitions->get($permission)['scope'] ?? null, $permission);
        }
        self::assertCount(count($definitions), $definitions->keys()->unique());
        self::assertSame($expected, array_values(Phase1Policy::ABILITIES));
    }

    public function test_permission_evaluator_source_contains_no_permission_cache(): void
    {
        $source = file_get_contents(app_path('Modules/Authorization/Application/PermissionEvaluator.php')) ?: '';

        self::assertStringNotContainsString('Cache::', $source);
        self::assertStringNotContainsString('remember(', $source);
        self::assertStringContainsString("whereNull('revoked_at')", $source);
    }
}
