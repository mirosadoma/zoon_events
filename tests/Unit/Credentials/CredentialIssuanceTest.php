<?php

namespace Tests\Unit\Credentials;

use App\Modules\Credentials\Application\Signing\CanonicalCredentialToken;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('phase-1')]
#[Group('free-registration')]
final class CredentialIssuanceTest extends TestCase
{
    public function test_compact_signed_token_contains_only_pii_free_entitlement_claims(): void
    {
        $token = app(CanonicalCredentialToken::class)->issue([
            'cid' => '01SYNTHETICCREDENTIAL000000',
            'eid' => '01SYNTHETICEVENT0000000000',
            'exp' => CarbonImmutable::now()->addHour()->getTimestamp(),
            'iat' => CarbonImmutable::now()->getTimestamp(),
            'nonce' => 'synthetic-nonce',
            'tid' => '01SYNTHETICTENANT000000000',
        ]);
        $claims = app(CanonicalCredentialToken::class)->verify($token);

        self::assertSame(['cid', 'eid', 'exp', 'iat', 'nonce', 'tid'], array_keys($claims));
        self::assertStringNotContainsString('@', $token);
        self::assertStringNotContainsString('name', mb_strtolower($token));
        self::assertLessThan(1024, strlen($token));
    }

    public function test_tampered_token_fails_signature_validation(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(CanonicalCredentialToken::class)->verify('zt1.phase1-test.invalid.invalid');
    }
}
