<?php

namespace Tests\Unit\Credentials;

use App\Modules\Credentials\Application\Signing\ArraySecretReferenceLoader;
use App\Modules\Credentials\Application\Signing\CanonicalCredentialToken;
use App\Modules\Credentials\Application\Signing\CredentialKeyRing;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('phase-1')]
final class CredentialKeyRingTest extends TestCase
{
    public function test_active_key_signs_and_verify_only_key_verifies_after_rotation(): void
    {
        $old = $this->key('verify_only', 'old-secret');
        $current = $this->key('active', 'current-secret');
        $ring = new CredentialKeyRing('current', ['old' => $old['metadata'], 'current' => $current['metadata']], new ArraySecretReferenceLoader([
            'current-secret' => $current['secret'],
            'old-secret' => $old['secret'],
        ]));

        $signature = sodium_bin2base64(
            sodium_crypto_sign_detached('old-message', sodium_base642bin($old['secret'], SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING)),
            SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING,
        );

        self::assertTrue($ring->verify('old', 'old-message', $signature));
        self::assertSame('current', $ring->sign('new-message')['key_id']);
        self::assertTrue($ring->isReady());
    }

    public function test_retired_compromised_and_unknown_keys_fail_closed(): void
    {
        $active = $this->key('active', 'active-secret');
        $retired = $this->key('retired', 'retired-secret');
        $ring = new CredentialKeyRing('active', ['active' => $active['metadata'], 'retired' => $retired['metadata']], new ArraySecretReferenceLoader([
            'active-secret' => $active['secret'],
        ]));

        self::assertFalse($ring->verify('retired', 'message', 'invalid'));
        self::assertFalse($ring->verify('missing', 'message', 'invalid'));
    }

    public function test_canonical_token_detects_tampering(): void
    {
        $active = $this->key('active', 'active-secret');
        $ring = new CredentialKeyRing('active', ['active' => $active['metadata']], new ArraySecretReferenceLoader([
            'active-secret' => $active['secret'],
        ]));
        $codec = new CanonicalCredentialToken($ring);
        $claims = [
            'cid' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'eid' => '01ARZ3NDEKTSV4RRFFQ69G5FAW',
            'iat' => 100,
            'exp' => 200,
            'nonce' => 'synthetic-nonce',
            'tid' => '01ARZ3NDEKTSV4RRFFQ69G5FAX',
        ];
        $token = $codec->issue($claims);
        ksort($claims, SORT_STRING);

        self::assertSame($claims, $codec->verify($token));
        $this->expectException(InvalidArgumentException::class);
        $codec->verify($token.'x');
    }

    /** @return array{metadata:array{status:string,public_key:string,private_key_reference:string},secret:string} */
    private function key(string $status, string $reference): array
    {
        $pair = sodium_crypto_sign_keypair();

        return [
            'metadata' => [
                'status' => $status,
                'public_key' => sodium_bin2base64(sodium_crypto_sign_publickey($pair), SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING),
                'private_key_reference' => $reference,
            ],
            'secret' => sodium_bin2base64(sodium_crypto_sign_secretkey($pair), SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING),
        ];
    }
}
