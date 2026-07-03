<?php

namespace Tests\Unit\Tenancy;

use App\Exceptions\FoundationException;
use App\Modules\Tenancy\Domain\Configuration\ConfigurationSchemaRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('tenant-configuration')]
class ConfigurationSchemaRegistryTest extends TestCase
{
    #[DataProvider('validValues')]
    public function test_versioned_configuration_values_are_validated(string $key, array $value): void
    {
        (new ConfigurationSchemaRegistry)->validate($key, $value);

        self::assertTrue(true);
    }

    public function test_secret_cross_tenant_and_malformed_values_are_rejected(): void
    {
        $registry = new ConfigurationSchemaRegistry;

        foreach ([
            ['branding', ['logo_asset_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV', 'nested' => ['token' => 'secret']]],
            ['domains', ['primary_domain' => 'not a hostname']],
            ['residency', ['region' => 'unapproved']],
            ['retention', ['audit_days' => 1]],
            ['branding', ['tenant_id' => 'foreign']],
        ] as [$key, $value]) {
            try {
                $registry->validate($key, $value);
                self::fail("Invalid {$key} configuration was accepted.");
            } catch (FoundationException $exception) {
                self::assertContains($exception->problemCode, ['configuration_secret_forbidden', 'configuration_value_invalid']);
            }
        }
    }

    public static function validValues(): array
    {
        return [
            'branding' => ['branding', ['logo_asset_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV']],
            'domains' => ['domains', ['primary_domain' => 'admin.example.test']],
            'residency' => ['residency', ['region' => 'ksa-central']],
            'retention' => ['retention', ['audit_days' => 365]],
        ];
    }
}
