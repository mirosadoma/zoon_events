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

        $this->validateRegistration($issues);
        $this->validatePersonalDataKeys($issues);
        $this->validateCredentialKeys($issues);
        $this->validatePaymentAdapter($issues);
        $this->validateNotificationAdapters($issues);
        $this->validateWalletAdapters($issues);

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
            if ((string) config('payments.default') === 'fake') {
                $issues[] = new ConfigurationIssue('PAYMENTS_DEFAULT_ADAPTER', 'testing_adapter_in_production', 'A testing-only payment adapter cannot be enabled in production.');
            }
            if (in_array((string) config('notifications.email_adapter'), ['fake', 'log'], true)) {
                $issues[] = new ConfigurationIssue('NOTIFICATIONS_EMAIL_ADAPTER', 'testing_adapter_in_production', 'A testing-only email adapter cannot be enabled in production.');
            }
            if ((string) config('notifications.sms_adapter') === 'fake') {
                $issues[] = new ConfigurationIssue('NOTIFICATIONS_SMS_ADAPTER', 'testing_adapter_in_production', 'A testing-only SMS adapter cannot be enabled in production.');
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

    private function validateRegistration(array &$issues): void
    {
        if ((int) config('registration.hold_minutes') < 5 || (int) config('registration.hold_minutes') > 60) {
            $issues[] = new ConfigurationIssue('REGISTRATION_HOLD_MINUTES', 'registration_hold_invalid', 'Registration hold duration must be between 5 and 60 minutes.');
        }
        if ((int) config('registration.max_form_fields') < 1 || (int) config('registration.max_form_fields') > 100) {
            $issues[] = new ConfigurationIssue('REGISTRATION_MAX_FORM_FIELDS', 'registration_form_limit_invalid', 'Registration form field limit must be between 1 and 100.');
        }
    }

    private function validatePersonalDataKeys(array &$issues): void
    {
        $keyId = (string) config('credentials.personal_data_current_key_id');
        $encoded = config("credentials.personal_data_key_ring.{$keyId}");
        $decoded = is_string($encoded) ? base64_decode($encoded, true) : false;
        if ($keyId === '' || ! is_string($decoded) || strlen($decoded) !== SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES) {
            $issues[] = new ConfigurationIssue('PERSONAL_DATA_KEY_RING', 'personal_data_key_unavailable', 'The current personal-data encryption key is unavailable or invalid.');
        }

        $blindKeyId = (string) config('credentials.blind_index_current_key_id');
        $blindKey = config("credentials.blind_index_key_ring.{$blindKeyId}");
        if ($blindKeyId === '' || ! is_string($blindKey) || strlen($blindKey) < 16) {
            $issues[] = new ConfigurationIssue('BLIND_INDEX_KEY_RING', 'blind_index_key_unavailable', 'The current blind-index key is unavailable or invalid.');
        }
    }

    private function validateCredentialKeys(array &$issues): void
    {
        $keyId = (string) config('credentials.current_key_id');
        $key = config("credentials.key_ring.{$keyId}");
        if ($keyId === '' || ! is_array($key) || ($key['status'] ?? null) !== 'active') {
            $issues[] = new ConfigurationIssue('CREDENTIAL_KEY_RING', 'credential_signing_key_unavailable', 'The current credential signing key must exist and be active.');

            return;
        }

        try {
            $publicKey = sodium_base642bin((string) ($key['public_key'] ?? ''), SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
        } catch (\Throwable) {
            $publicKey = '';
        }
        if (strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES || trim((string) ($key['private_key_reference'] ?? '')) === '') {
            $issues[] = new ConfigurationIssue('CREDENTIAL_KEY_RING', 'credential_signing_key_invalid', 'The current credential signing key metadata is invalid.');
        }
    }

    private function validatePaymentAdapter(array &$issues): void
    {
        $adapter = (string) config('payments.default');
        if (! in_array($adapter, ['disabled', 'fake', 'moyasar'], true)) {
            $issues[] = new ConfigurationIssue('PAYMENTS_DEFAULT_ADAPTER', 'payment_adapter_invalid', 'The configured payment adapter is not supported.');
        }
        if ($adapter === 'moyasar') {
            $this->required($issues, 'MOYASAR_SECRET_REFERENCE', config('payments.moyasar.secret_reference'));
            $this->required($issues, 'MOYASAR_WEBHOOK_SECRET_REFERENCE', config('payments.moyasar.webhook_secret_reference'));
            if (! (bool) config('payments.allow_network')) {
                $issues[] = new ConfigurationIssue('PAYMENTS_ALLOW_NETWORK', 'payment_network_disabled', 'Network access must be explicitly enabled for a live payment adapter.');
            }
        }
    }

    private function validateNotificationAdapters(array &$issues): void
    {
        if (! in_array((string) config('notifications.email_adapter'), ['disabled', 'log', 'fake', 'smtp'], true)) {
            $issues[] = new ConfigurationIssue('NOTIFICATIONS_EMAIL_ADAPTER', 'email_adapter_invalid', 'The configured email adapter is not supported.');
        }

        $sms = (string) config('notifications.sms_adapter');
        if (! in_array($sms, ['disabled', 'fake', 'unifonic'], true)) {
            $issues[] = new ConfigurationIssue('NOTIFICATIONS_SMS_ADAPTER', 'sms_adapter_invalid', 'The configured SMS adapter is not supported.');
        }
        if ($sms === 'unifonic') {
            $this->required($issues, 'UNIFONIC_APP_SID_REFERENCE', config('notifications.unifonic.app_sid_reference'));
            $this->required($issues, 'UNIFONIC_SENDER_ID', config('notifications.unifonic.sender_id'));
            if (! (bool) config('notifications.allow_network')) {
                $issues[] = new ConfigurationIssue('NOTIFICATIONS_ALLOW_NETWORK', 'notification_network_disabled', 'Network access must be explicitly enabled for a live notification adapter.');
            }
        }
    }

    private function validateWalletAdapters(array &$issues): void
    {
        $apple = (string) config('wallet.default_apple_adapter');
        $google = (string) config('wallet.default_google_adapter');

        if (! in_array($apple, ['fake', 'apple'], true)) {
            $issues[] = new ConfigurationIssue('WALLET_APPLE_ADAPTER', 'wallet_adapter_invalid', 'The configured Apple wallet adapter is not supported.');
        }
        if (! in_array($google, ['fake', 'google'], true)) {
            $issues[] = new ConfigurationIssue('WALLET_GOOGLE_ADAPTER', 'wallet_adapter_invalid', 'The configured Google wallet adapter is not supported.');
        }

        if (app()->environment('production', 'staging') && $apple === 'fake') {
            $issues[] = new ConfigurationIssue('WALLET_APPLE_ADAPTER', 'testing_adapter_in_production', 'Fake Apple wallet adapter cannot be used in production.');
        }
        if (app()->environment('production', 'staging') && $google === 'fake') {
            $issues[] = new ConfigurationIssue('WALLET_GOOGLE_ADAPTER', 'testing_adapter_in_production', 'Fake Google wallet adapter cannot be used in production.');
        }

        if ($apple === 'apple') {
            if (trim((string) config('wallet.apple.certificate_secret_reference')) === '') {
                $issues[] = new ConfigurationIssue('WALLET_APPLE_CERT_SECRET_REF', 'wallet_configuration_invalid', 'Apple wallet certificate secret reference is required for the live adapter.');
            }
            if (trim((string) config('wallet.apple.private_key_secret_reference')) === '') {
                $issues[] = new ConfigurationIssue('WALLET_APPLE_KEY_SECRET_REF', 'wallet_configuration_invalid', 'Apple wallet private key secret reference is required for the live adapter.');
            }
            if (trim((string) config('wallet.apple.pass_type_identifier')) === '') {
                $issues[] = new ConfigurationIssue('WALLET_APPLE_PASS_TYPE_IDENTIFIER', 'wallet_configuration_invalid', 'Apple pass type identifier is required for the live adapter.');
            }
            if (trim((string) config('wallet.apple.team_identifier')) === '') {
                $issues[] = new ConfigurationIssue('WALLET_APPLE_TEAM_IDENTIFIER', 'wallet_configuration_invalid', 'Apple team identifier is required for the live adapter.');
            }
            if (trim((string) config('wallet.apple.web_service_base_url')) === '') {
                $issues[] = new ConfigurationIssue('WALLET_APPLE_WEB_SERVICE_URL', 'wallet_configuration_invalid', 'Apple wallet web service base URL is required for the live adapter.');
            }
        }

        if ($google === 'google') {
            if (trim((string) config('wallet.google.service_account_secret_reference')) === '') {
                $issues[] = new ConfigurationIssue('WALLET_GOOGLE_SERVICE_ACCOUNT_SECRET_REF', 'wallet_configuration_invalid', 'Google wallet service account secret reference is required for the live adapter.');
            }
            if (trim((string) config('wallet.google.issuer_id')) === '') {
                $issues[] = new ConfigurationIssue('WALLET_GOOGLE_ISSUER_ID', 'wallet_configuration_invalid', 'Google Wallet issuer ID is required for the live adapter.');
            }
        }
    }
}
