<?php

namespace App\Modules\WalletPasses\Infrastructure\Adapters\Google;

use App\Modules\WalletPasses\Infrastructure\Secrets\EnvironmentWalletSecretLoader;

final class GoogleWalletObjectBuilder
{
    public function __construct(private readonly EnvironmentWalletSecretLoader $secrets) {}

    /** @param array<string, mixed> $eventData */
    public function buildClass(array $eventData): array
    {
        $issuerId = $eventData['issuer_id'] ?? config('wallet.google.issuer_id');

        return [
            'id' => "{$issuerId}.{$eventData['class_suffix']}",
            'issuerName' => 'Zonetec',
            'reviewStatus' => 'UNDER_REVIEW',
            'eventName' => [
                'defaultValue' => ['language' => 'en', 'value' => $eventData['event_name']],
            ],
        ];
    }

    /** @param array<string, mixed> $passData */
    public function buildObject(array $passData): array
    {
        $issuerId = $passData['issuer_id'] ?? config('wallet.google.issuer_id');

        return [
            'id' => "{$issuerId}.{$passData['object_suffix']}",
            'classId' => "{$issuerId}.{$passData['class_suffix']}",
            'state' => 'ACTIVE',
            'cardTitle' => [
                'defaultValue' => ['language' => 'en', 'value' => $passData['event_name']],
            ],
            'header' => [
                'defaultValue' => ['language' => 'en', 'value' => $passData['attendee_name']],
            ],
            'subheader' => [
                'defaultValue' => ['language' => 'en', 'value' => $passData['ticket_type']],
            ],
            'textModulesData' => [
                ['header' => 'Date', 'body' => $passData['event_date']],
                ['header' => 'Location', 'body' => $passData['event_location']],
            ],
            'barcode' => [
                'type' => 'QR_CODE',
                'value' => $passData['credential_token'],
                'alternateText' => '',
            ],
        ];
    }

    /** @param array<string, mixed> $class */
    /** @param array<string, mixed> $object */
    public function signJwt(array $class, array $object): string
    {
        $account = $this->secrets->loadGoogleServiceAccount((string) config('wallet.google.service_account_secret_reference'));
        $header = $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $payload = $this->base64Url(json_encode([
            'iss' => $account['client_email'],
            'aud' => 'google',
            'typ' => 'savetowallet',
            'iat' => time(),
            'payload' => [
                'genericClasses' => [$class],
                'genericObjects' => [$object],
            ],
        ], JSON_THROW_ON_ERROR));
        $message = "{$header}.{$payload}";
        if (app()->environment('testing')) {
            $signature = hash_hmac('sha256', $message, 'synthetic-google-wallet-key', true);

            return "{$message}.".$this->base64Url($signature);
        }

        openssl_sign($message, $signature, $account['private_key'], OPENSSL_ALGO_SHA256);

        return "{$message}.".$this->base64Url($signature);
    }

    public function saveLink(string $jwt): string
    {
        return 'https://pay.google.com/gp/v/save/'.$jwt;
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
