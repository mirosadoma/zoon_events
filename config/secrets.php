<?php

/**
 * Named secrets resolved while config is loading so they remain available
 * after `php artisan config:cache` (when the .env file is no longer read).
 *
 * Runtime code must use EnvironmentValue::get() / config('secrets.*'),
 * never rely on env()/Env::get() alone outside this file.
 */

$referenceNames = [];

$push = static function (?string $name) use (&$referenceNames): void {
    $name = trim((string) $name);
    if ($name !== '') {
        $referenceNames[$name] = true;
    }
};

$pushFromKeyRing = static function (?string $json) use ($push): void {
    $decoded = json_decode((string) $json, true);
    if (! is_array($decoded)) {
        return;
    }

    foreach ($decoded as $entry) {
        if (! is_array($entry)) {
            continue;
        }

        $push($entry['private_key_reference'] ?? null);
        $push($entry['secret_reference'] ?? null);
        $push($entry['key_reference'] ?? null);
    }
};

$pushFromKeyRing(env('CREDENTIAL_KEY_RING'));
$pushFromKeyRing(env('PERSONAL_DATA_KEY_RING'));
$pushFromKeyRing(env('BLIND_INDEX_KEY_RING'));
$pushFromKeyRing(env('AUDIT_KEY_RING'));

$push(env('MOYASAR_SECRET_REFERENCE'));
$push(env('MOYASAR_WEBHOOK_SECRET_REFERENCE'));
$push(env('UNIFONIC_APP_SID_REFERENCE'));
$push(env('WALLET_APPLE_CERT_SECRET_REF'));
$push(env('WALLET_APPLE_KEY_SECRET_REF'));
$push(env('WALLET_GOOGLE_SERVICE_ACCOUNT_SECRET_REF'));

// Always include the common local credential private key name when present.
$push('CREDENTIAL_TEST_PRIVATE_KEY');

$secrets = [];
foreach (array_keys($referenceNames) as $name) {
    $value = env($name);
    if (is_string($value) && $value !== '') {
        $secrets[$name] = $value;
    }
}

return $secrets;
