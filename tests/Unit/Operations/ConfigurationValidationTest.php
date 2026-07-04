<?php

namespace Tests\Unit\Operations;

use App\Modules\Operations\Application\Configuration\ConfigurationValidator;
use App\Modules\Operations\Application\Health\Checks\CredentialSigningHealthCheck;
use App\Modules\Operations\Application\Health\Checks\DataProtectionHealthCheck;
use App\Modules\Operations\Application\Health\Checks\NotificationConfigurationHealthCheck;
use App\Modules\Operations\Application\Health\Checks\PaymentConfigurationHealthCheck;
use Tests\TestCase;

class ConfigurationValidationTest extends TestCase
{
    public function test_valid_testing_configuration_contains_no_issues(): void
    {
        self::assertSame([], app(ConfigurationValidator::class)->validate());
    }

    public function test_missing_audit_key_reports_key_not_secret_value(): void
    {
        config(['audit.current_key_id' => 'missing', 'audit.key_ring' => []]);
        $encoded = json_encode(array_map(fn ($issue) => $issue->toArray(), app(ConfigurationValidator::class)->validate()));

        self::assertStringContainsString('AUDIT_KEY_RING', $encoded);
        self::assertStringNotContainsString((string) config('app.key'), $encoded);
    }

    public function test_phase_one_health_categories_are_ready_with_safe_testing_configuration(): void
    {
        foreach ([
            DataProtectionHealthCheck::class,
            CredentialSigningHealthCheck::class,
            PaymentConfigurationHealthCheck::class,
            NotificationConfigurationHealthCheck::class,
        ] as $check) {
            self::assertSame('ok', app($check)->run()->status);
        }
    }

    public function test_live_adapter_configuration_fails_closed_without_leaking_values(): void
    {
        config([
            'payments.default' => 'moyasar',
            'payments.allow_network' => false,
            'payments.moyasar.secret_reference' => 'highly-sensitive-reference',
            'payments.moyasar.webhook_secret_reference' => null,
            'notifications.sms_adapter' => 'unifonic',
            'notifications.allow_network' => false,
            'notifications.unifonic.app_sid_reference' => 'another-sensitive-reference',
            'notifications.unifonic.sender_id' => null,
        ]);

        $encoded = json_encode(array_map(
            fn ($issue) => $issue->toArray(),
            app(ConfigurationValidator::class)->validate(),
        ));

        self::assertStringContainsString('payment_network_disabled', $encoded);
        self::assertStringContainsString('notification_network_disabled', $encoded);
        self::assertStringNotContainsString('highly-sensitive-reference', $encoded);
        self::assertStringNotContainsString('another-sensitive-reference', $encoded);
    }
}
