<?php

namespace App\Modules\Operations\Application\Configuration;

final class ConfigurationValidator
{
    /** @return list<ConfigurationIssue> */
    public function validate(): array
    {
        $issues = [];
        $this->requiredSecret($issues, 'APP_KEY', config('app.key'));
        $this->required($issues, 'APP_URL', config('app.url'));
        $this->required($issues, 'DB_CONNECTION', config('database.default'));
        $this->required($issues, 'QUEUE_CONNECTION', config('queue.default'));

        $keyId = (string) config('audit.current_key_id');
        $key = config("audit.key_ring.{$keyId}");
        if ($keyId === '' || ! is_string($key) || strlen($key) < 16) {
            $issues[] = new ConfigurationIssue('AUDIT_KEY_RING', 'audit_key_unavailable', 'The current audit integrity key must exist and contain at least 16 characters.');
        }

        if (app()->environment('production')) {
            if ((bool) config('app.debug')) {
                $issues[] = new ConfigurationIssue('APP_DEBUG', 'unsafe_production_debug', 'Debug mode must be disabled in production.');
            }
            if (! str_starts_with((string) config('app.url'), 'https://')) {
                $issues[] = new ConfigurationIssue('APP_URL', 'unsafe_production_url', 'Production application URL must use HTTPS.');
            }
            if ((string) config('integrations.default_adapter') === 'fake') {
                $issues[] = new ConfigurationIssue('INTEGRATIONS_DEFAULT_ADAPTER', 'testing_adapter_in_production', 'A testing-only adapter cannot be the production default.');
            }
        }

        if ((int) config('audit.export_expiry_minutes') < 5 || (int) config('audit.export_expiry_minutes') > 1440) {
            $issues[] = new ConfigurationIssue('AUDIT_EXPORT_EXPIRY_MINUTES', 'audit_export_expiry_invalid', 'Audit export expiry must be between 5 and 1440 minutes.');
        }

        return $issues;
    }

    public function isValid(): bool
    {
        return $this->validate() === [];
    }

    private function required(array &$issues, string $key, mixed $value): void
    {
        if ($value === null || $value === '') {
            $issues[] = new ConfigurationIssue($key, 'configuration_required', "Required configuration {$key} is missing.");
        }
    }

    private function requiredSecret(array &$issues, string $key, mixed $value): void
    {
        if (! is_string($value) || strlen($value) < 16) {
            $issues[] = new ConfigurationIssue($key, 'secret_required', "Required secret {$key} is missing or too short.");
        }
    }
}
