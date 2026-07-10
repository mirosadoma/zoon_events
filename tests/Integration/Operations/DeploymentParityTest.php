<?php

namespace Tests\Integration\Operations;

use App\Modules\Operations\Application\Configuration\ConfigurationValidator;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('deployment-parity')]
class DeploymentParityTest extends TestCase
{
    public function test_saas_and_on_premise_profiles_keep_security_contracts_identical(): void
    {
        $baseline = [
            config('tenancy.shared_schema'),
            config('feature-flags.non_flaggable'),
            config('audit.hmac_algorithm'),
            config('queue.default'),
        ];
        foreach (['saas', 'on_premise'] as $mode) {
            config(['zonetec.deployment_mode' => $mode, 'integrations.allow_network' => false]);
            self::assertSame($baseline, [
                config('tenancy.shared_schema'),
                config('feature-flags.non_flaggable'),
                config('audit.hmac_algorithm'),
                config('queue.default'),
            ]);
            self::assertTrue(app(ConfigurationValidator::class)->isValid());
        }
    }
}
