<?php

namespace App\Modules\WalletPasses\Infrastructure\Secrets;

final class SyntheticWalletMaterial
{
    private const APPLE_CERT = <<<'PEM'
-----BEGIN CERTIFICATE-----
MIIDazCCAlOgAwIBAgIUKKx0vJ8x8mQv0n8x8mQv0n8wDQYJKoZIhvcNAQELBQAw
TzELMAkGA1UEBhMCVVMxEzARBgNVBAgMClNvbWUtU3RhdGUxITAfBgNVBAoM
GEludGVybmV0IFdpZGdpdHMgUHR5IEx0ZDEWMBQGA1UEAwwNem9uZXRlYy10ZXN0
MB4XDTI0MDEwMTAwMDAwMFoXDTM0MDEwMTAwMDAwMFowTzELMAkGA1UEBhMCVVMx
EzARBgNVBAgMClNvbWUtU3RhdGUxITAfBgNVBAoMGEludGVybmV0IFdpZGdpdHMg
UHR5IEx0ZDEWMBQGA1UEAwwNem9uZXRlYy10ZXN0MIIBIjANBgkqhkiG9w0BAQEF
AAOCAQ8AMIIBCgKCAQEAu1SU1LfVLPHCozMxH2Mo4lgOEePzNm0tRgeLezV6ffAt
0gunVTLw7onLRnrq0/IzW7yWR7QkrmBL7jTKKe5k3w4HCTmBJgbBM7LVMfTKj4zAyL
YFqeGdQ/5IYzYqWDJKJI3qF0QD4CsYuA8xx8A29qEdORxFTvej8YHeCgFuoLMzOhM
AIDAQABo1MwUTAdBgNVHQ4EFgQU0n8x8mQv0n8x8mQv0n8x8mQv0n8wHwYDVR0jBB
gwFoAU0n8x8mQv0n8x8mQv0n8x8mQv0n8wDwYDVR0TAQH/BAUwAwEB/zANBgkq
hkiG9w0BAQsFAAOCAQEAvxH8mQv0n8x8mQv0n8x8mQv0n8x8mQv0n8x8mQv0n8x
8mQv0n8x8mQv0n8x8mQv0n8x8mQv0n8x8mQv0n8x8mQv0n8x8mQv0n8x8mQv0n8
-----END CERTIFICATE-----
PEM;

    private const APPLE_KEY = <<<'PEM'
-----BEGIN PRIVATE KEY-----
MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQC7VJTUt9Us8cKj
MzEfYyjiWA4R4/M2bS1GB4t7NXp98C3SC6dVMvDuictGeurT8jNbvJZHtCSuYEvu
NMop7mTfDgcJMYEmBsEztUxd8qPjMDItgWp4Z1D/khjNipYMkoon0oXRA+ArGLgP
McfADf2oR05HEVO96Pxgd4KAW6gszM6EwIDAQABAoIBAQC7VJTU
-----END PRIVATE KEY-----
PEM;

    public static function appleCertificate(): string
    {
        $path = base_path('tests/fixtures/wallet/apple-cert.pem');
        if (is_file($path)) {
            return file_get_contents($path) ?: self::APPLE_CERT;
        }

        return self::APPLE_CERT;
    }

    public static function applePrivateKey(): string
    {
        $path = base_path('tests/fixtures/wallet/apple-key.pem');
        if (is_file($path)) {
            return file_get_contents($path) ?: self::APPLE_KEY;
        }

        return self::APPLE_KEY;
    }

    /** @return array<string, mixed> */
    public static function googleServiceAccount(): array
    {
        $path = base_path('tests/fixtures/wallet/google-service-account.json');
        if (is_file($path)) {
            $decoded = json_decode(file_get_contents($path) ?: '{}', true);

            return is_array($decoded) ? $decoded : self::fallbackGoogleAccount();
        }

        return self::fallbackGoogleAccount();
    }

    /** @return array<string, mixed> */
    private static function fallbackGoogleAccount(): array
    {
        $keyPath = base_path('tests/fixtures/wallet/google-key.pem');
        $privateKey = is_file($keyPath) ? (file_get_contents($keyPath) ?: '') : '';

        return [
            'client_email' => 'wallet-testing@zonetec-testing.iam.gserviceaccount.com',
            'private_key' => $privateKey,
            'private_key_id' => 'synthetic-key-id',
            'client_id' => '000000000000000000000',
        ];
    }
}
