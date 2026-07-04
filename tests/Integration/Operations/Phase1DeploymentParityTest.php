<?php

namespace Tests\Integration\Operations;

use App\Modules\Notifications\Application\NotificationAdapterRegistry;
use App\Modules\Payments\Application\PaymentGatewayRegistry;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('phase-1')]
#[Group('phase-1-deployment-parity')]
final class Phase1DeploymentParityTest extends TestCase
{
    public function test_local_core_and_fake_recovery_adapters_boot_in_both_modes_with_network_blocked(): void
    {
        foreach (['saas', 'on_premise'] as $mode) {
            config()->set('zonetec.deployment_mode', $mode);
            config()->set('notifications.allow_network', false);
            config()->set('payments.allow_network', false);
            self::assertNotNull(app(NotificationAdapterRegistry::class)->get('fake'));
            self::assertNotNull(app(PaymentGatewayRegistry::class)->get('fake'));
            self::assertSame('database', config('queue.default'));
        }
    }
}
