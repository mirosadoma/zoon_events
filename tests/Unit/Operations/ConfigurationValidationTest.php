<?php

namespace Tests\Unit\Operations;

use App\Modules\Operations\Application\Configuration\ConfigurationValidator;
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
}
