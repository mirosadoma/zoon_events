<?php

namespace Tests\Unit\WalletPasses;

use App\Modules\WalletPasses\Infrastructure\Adapters\Apple\ApplePassBuilder;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;
use ZipArchive;

#[Group('phase-2')]
#[Group('wallet-passes')]
final class ApplePassBuilderTest extends TestCase
{
    public function test_built_pass_bundle_contains_required_fields_and_safe_manifest_digests(): void
    {
        $bundle = app(ApplePassBuilder::class)->build([
            'pass_type_identifier' => 'pass.com.synthetic.event',
            'team_identifier' => 'SYNTHETIC',
            'serial_number' => '01SYNTHETICSERIAL0000000000',
            'organization_name' => 'Synthetic Org',
            'description' => 'Synthetic Event Pass',
            'event_name' => 'Synthetic Summit',
            'event_date' => '2027-01-10T12:00:00Z',
            'event_location' => 'Riyadh',
            'attendee_name' => 'Synthetic Attendee',
            'ticket_type' => 'General Admission',
            'credential_token' => 'zt1.synthetic-credential-token',
        ]);

        $archive = new ZipArchive;
        self::assertTrue($archive->open($bundle->path));
        $passJson = $archive->getFromName('pass.json');
        $manifestJson = $archive->getFromName('manifest.json');
        $archive->close();

        self::assertIsString($passJson);
        self::assertIsString($manifestJson);
        $pass = json_decode($passJson, true, flags: JSON_THROW_ON_ERROR);
        $manifest = json_decode($manifestJson, true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('https://wallet.test/apple/v1', $pass['webServiceURL'] ?? null);
        self::assertNotEmpty($pass['authenticationToken'] ?? null);
        self::assertSame('Synthetic Summit', $pass['eventName'] ?? $pass['event']['name'] ?? null);
        self::assertSame('Synthetic Attendee', $pass['attendeeName'] ?? $pass['attendee']['name'] ?? null);
        self::assertSame('General Admission', $pass['ticketType'] ?? $pass['ticket']['type'] ?? null);
        self::assertSame('zt1.synthetic-credential-token', $pass['barcodes'][0]['message'] ?? null);

        $serialized = mb_strtolower($passJson);
        self::assertStringNotContainsString('national_id', $serialized);
        self::assertStringNotContainsString('biometric', $serialized);
        self::assertStringNotContainsString('payment_card', $serialized);

        foreach (['pass.json', 'manifest.json', 'signature'] as $file) {
            self::assertArrayHasKey($file, $manifest);
            self::assertMatchesRegularExpression('/^[a-f0-9]{40}$|^[a-f0-9]{64}$/', $manifest[$file]);
        }
    }
}
