<?php

namespace Tests\Contract\Credentials;

use App\Modules\Credentials\Application\Signing\CanonicalCredentialToken;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('phase-1')]
#[Group('credentials')]
final class CredentialContractTest extends TestCase
{
    public function test_zt1_token_has_canonical_pii_free_claims_and_bounded_size(): void
    {
        $token = app(CanonicalCredentialToken::class)->issue([
            'cid' => '01SYNTHETICCREDENTIAL000000',
            'eid' => '01SYNTHETICEVENT0000000000',
            'exp' => CarbonImmutable::now()->addHour()->timestamp,
            'iat' => CarbonImmutable::now()->timestamp,
            'nonce' => 'nonce-safe',
            'tid' => '01SYNTHETICTENANT000000000',
        ]);
        self::assertStringStartsWith('zt1.', $token);
        self::assertLessThanOrEqual(2048, strlen($token));
        self::assertSame(
            ['cid', 'eid', 'exp', 'iat', 'nonce', 'tid'],
            array_keys(app(CanonicalCredentialToken::class)->verify($token)),
        );
        self::assertStringNotContainsString('@', $token);
    }
}
