<?php

namespace App\Modules\Tenancy\Domain\Configuration;

use App\Exceptions\FoundationException;

final class ConfigurationSchemaRegistry
{
    public function definitions(): array
    {
        return [
            'branding' => ['version' => 1, 'owner' => 'tenancy', 'purpose' => 'Validated branding references only.', 'sensitivity' => 'internal', 'value_schema' => ['type' => 'object', 'properties' => ['logo_asset_id' => ['type' => 'string']]]],
            'domains' => ['version' => 1, 'owner' => 'tenancy', 'purpose' => 'Validated domain references only.', 'sensitivity' => 'internal', 'value_schema' => ['type' => 'object', 'properties' => ['primary_domain' => ['type' => 'string', 'format' => 'hostname']]]],
            'residency' => ['version' => 1, 'owner' => 'operations', 'purpose' => 'Approved residency policy.', 'sensitivity' => 'confidential', 'value_schema' => ['type' => 'object', 'properties' => ['region' => ['type' => 'string', 'enum' => ['ksa-central', 'ksa-west', 'test']]]]],
            'retention' => ['version' => 1, 'owner' => 'operations', 'purpose' => 'Approved retention policy.', 'sensitivity' => 'confidential', 'value_schema' => ['type' => 'object', 'properties' => ['audit_days' => ['type' => 'integer', 'minimum' => 30, 'maximum' => 3650]]]],
        ];
    }

    public function validate(string $key, array $value): void
    {
        $definition = $this->definitions()[$key] ?? null;
        if ($definition === null) {
            throw FoundationException::validation('configuration_schema_unknown', 'The configuration schema is not supported.');
        }

        $encoded = json_encode($value, JSON_THROW_ON_ERROR);
        if (preg_match('/"(?:[^"]*password|[^"]*token|[^"]*secret|[^"]*credential|tenant_id)"\s*:/i', $encoded)) {
            throw FoundationException::validation('configuration_secret_forbidden', 'Secret-bearing values are not accepted in tenant configuration.');
        }

        $valid = match ($key) {
            'branding' => array_keys($value) === ['logo_asset_id']
                && is_string($value['logo_asset_id'])
                && preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', $value['logo_asset_id']) === 1,
            'domains' => array_keys($value) === ['primary_domain']
                && is_string($value['primary_domain'])
                && filter_var($value['primary_domain'], FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false,
            'residency' => array_keys($value) === ['region']
                && in_array($value['region'], ['ksa-central', 'ksa-west', 'test'], true),
            'retention' => array_keys($value) === ['audit_days']
                && is_int($value['audit_days'])
                && $value['audit_days'] >= 30
                && $value['audit_days'] <= 3650,
        };

        if (! $valid) {
            throw FoundationException::validation('configuration_value_invalid', 'The configuration value does not match its versioned schema.');
        }
    }
}
