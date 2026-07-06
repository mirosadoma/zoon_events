<?php

namespace App\Modules\WalletPasses\Infrastructure\Adapters\Apple;

use App\Modules\WalletPasses\Infrastructure\Secrets\EnvironmentWalletSecretLoader;
use Illuminate\Support\Str;
use ZipArchive;

final class ApplePassBuilder
{
    public function __construct(private readonly EnvironmentWalletSecretLoader $secrets) {}

    /** @param array<string, mixed> $passData */
    public function build(array $passData): ApplePassBundle
    {
        $authenticationToken = Str::random(48);
        $pass = [
            'formatVersion' => 1,
            'passTypeIdentifier' => $passData['pass_type_identifier'] ?? config('wallet.apple.pass_type_identifier'),
            'teamIdentifier' => $passData['team_identifier'] ?? config('wallet.apple.team_identifier'),
            'serialNumber' => $passData['serial_number'],
            'webServiceURL' => config('wallet.apple.web_service_base_url'),
            'authenticationToken' => $authenticationToken,
            'organizationName' => $passData['organization_name'] ?? 'Zonetec',
            'description' => $passData['description'] ?? 'Event Pass',
            'eventName' => $passData['event_name'],
            'eventDate' => $passData['event_date'],
            'eventLocation' => $passData['event_location'],
            'attendeeName' => $passData['attendee_name'],
            'ticketType' => $passData['ticket_type'],
            'barcodes' => [[
                'format' => 'PKBarcodeFormatQR',
                'message' => $passData['credential_token'],
                'messageEncoding' => 'iso-8859-1',
            ]],
            'generic' => [
                'primaryFields' => [['key' => 'event', 'label' => 'Event', 'value' => $passData['event_name']]],
                'secondaryFields' => [['key' => 'attendee', 'label' => 'Attendee', 'value' => $passData['attendee_name']]],
                'auxiliaryFields' => [['key' => 'ticket', 'label' => 'Ticket', 'value' => $passData['ticket_type']]],
            ],
        ];

        if (! empty($passData['zone_tier_label'])) {
            $pass['generic']['auxiliaryFields'][] = [
                'key' => 'zone',
                'label' => 'Zone',
                'value' => $passData['zone_tier_label'],
            ];
        }

        $directory = storage_path('app/wallet-passes/'.Str::ulid());
        mkdir($directory, 0777, true);
        $passJsonPath = "{$directory}/pass.json";
        file_put_contents($passJsonPath, json_encode($pass, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        $manifest = [
            'pass.json' => sha1_file($passJsonPath),
        ];
        $manifestJsonPath = "{$directory}/manifest.json";
        file_put_contents($manifestJsonPath, json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $manifest['manifest.json'] = sha1_file($manifestJsonPath);

        $signaturePath = "{$directory}/signature";
        $this->signManifest($manifestJsonPath, $signaturePath);
        $manifest['signature'] = sha1_file($signaturePath);
        file_put_contents($manifestJsonPath, json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        $bundlePath = "{$directory}/pass.pkpass";
        $zip = new ZipArchive;
        $zip->open($bundlePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFile($passJsonPath, 'pass.json');
        $zip->addFile($manifestJsonPath, 'manifest.json');
        $zip->addFile($signaturePath, 'signature');
        $zip->close();

        return new ApplePassBundle($bundlePath, $authenticationToken, $bundlePath);
    }

    private function signManifest(string $manifestPath, string $signaturePath): void
    {
        if (app()->environment('testing')) {
            file_put_contents($signaturePath, 'synthetic-signature');

            return;
        }

        $certificate = $this->secrets->loadCertificate((string) config('wallet.apple.certificate_secret_reference'));
        $privateKey = $this->secrets->loadPrivateKey((string) config('wallet.apple.private_key_secret_reference'));
        $certFile = tempnam(sys_get_temp_dir(), 'wallet-cert');
        $keyFile = tempnam(sys_get_temp_dir(), 'wallet-key');
        file_put_contents($certFile, $certificate);
        file_put_contents($keyFile, $privateKey);

        openssl_pkcs7_sign(
            $manifestPath,
            $signaturePath,
            file_get_contents($certFile),
            [file_get_contents($keyFile), ''],
            [],
            PKCS7_DETACHED | PKCS7_BINARY,
        );

        @unlink($certFile);
        @unlink($keyFile);
    }
}
