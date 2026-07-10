<?php

namespace Tests\Unit\WalletPasses;

use App\Modules\WalletPasses\Infrastructure\Adapters\Google\GoogleWalletObjectBuilder;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('phase-2')]
#[Group('wallet-passes')]
final class GoogleWalletObjectBuilderTest extends TestCase
{
    public function test_built_generic_object_and_signed_save_link_match_the_phase_two_contract(): void
    {
        $builder = app(GoogleWalletObjectBuilder::class);
        $issuerId = '3388000000000000000';
        $classSuffix = 'synthetic-event';
        $objectSuffix = 'synthetic-pass';

        $class = $builder->buildClass([
            'issuer_id' => $issuerId,
            'class_suffix' => $classSuffix,
            'event_name' => 'Synthetic Summit',
        ]);
        $object = $builder->buildObject([
            'issuer_id' => $issuerId,
            'class_suffix' => $classSuffix,
            'object_suffix' => $objectSuffix,
            'event_name' => 'Synthetic Summit',
            'event_date' => '2027-01-10T12:00:00Z',
            'event_location' => 'Riyadh',
            'attendee_name' => 'Synthetic Attendee',
            'ticket_type' => 'General Admission',
            'credential_token' => 'zt1.synthetic-credential-token',
        ]);

        self::assertSame("{$issuerId}.{$objectSuffix}", $object['id']);
        self::assertSame("{$issuerId}.{$classSuffix}", $object['classId']);
        self::assertSame('Synthetic Summit', $object['cardTitle']['defaultValue']['value'] ?? $object['eventName'] ?? null);
        self::assertSame('zt1.synthetic-credential-token', $object['barcode']['value'] ?? $object['barcode']['alternateText'] ?? null);

        $jwt = $builder->signJwt($class, $object);
        self::assertCount(3, explode('.', $jwt));
        self::assertStringStartsWith('https://pay.google.com/gp/v/save/', $builder->saveLink($jwt));

        $payload = json_decode(base64_decode(strtr(explode('.', $jwt)[1], '-_', '+/')), true, flags: JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('payload', $payload);
        self::assertArrayHasKey('genericObjects', $payload['payload']);
    }
}
