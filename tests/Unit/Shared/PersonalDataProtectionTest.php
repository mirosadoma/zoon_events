<?php

namespace Tests\Unit\Shared;

use App\Modules\Shared\Application\DataProtection\BlindIndex;
use App\Modules\Shared\Application\DataProtection\PersonalDataCipher;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('phase-1')]
final class PersonalDataProtectionTest extends TestCase
{
    private string $key;

    protected function setUp(): void
    {
        $this->key = base64_encode(str_repeat('k', SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES));
    }

    public function test_authenticated_cipher_round_trips_and_detects_tampering(): void
    {
        $cipher = new PersonalDataCipher('current', ['current' => $this->key]);
        $encrypted = $cipher->encrypt('attendee@example.test', 'tenant:event:email');

        self::assertSame('current', $encrypted['key_id']);
        self::assertNotSame('attendee@example.test', $encrypted['ciphertext']);
        self::assertSame('attendee@example.test', $cipher->decrypt($encrypted, 'tenant:event:email'));

        $encrypted['ciphertext'][5] = $encrypted['ciphertext'][5] === 'A' ? 'B' : 'A';
        $this->expectException(InvalidArgumentException::class);
        $cipher->decrypt($encrypted, 'tenant:event:email');
    }

    public function test_old_key_can_be_read_during_rotation(): void
    {
        $old = base64_encode(str_repeat('o', SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES));
        $legacy = (new PersonalDataCipher('old', ['old' => $old]))->encrypt('value', 'scope');
        $rotated = new PersonalDataCipher('current', ['current' => $this->key, 'old' => $old]);

        self::assertSame('value', $rotated->decrypt($legacy, 'scope'));
        self::assertSame('current', $rotated->encrypt('value', 'scope')['key_id']);
    }

    public function test_blind_indexes_normalize_email_and_phone_without_revealing_values(): void
    {
        $index = new BlindIndex('v1', ['v1' => 'synthetic-index-key']);

        self::assertSame($index->email(' Test@Example.COM '), $index->email('test@example.com'));
        self::assertSame($index->phone('+966 50 123 4567'), $index->phone('00966-50-123-4567'));
        self::assertStringNotContainsString('example', $index->email('test@example.com'));
    }
}
