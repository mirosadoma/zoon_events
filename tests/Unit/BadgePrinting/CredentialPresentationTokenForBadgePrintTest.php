<?php

namespace Tests\Unit\BadgePrinting;

use App\Modules\Credentials\Application\Presentation\CredentialPresentationToken;
use App\Modules\Credentials\Infrastructure\Persistence\Models\Credential;
use App\Modules\Shared\Application\DataProtection\PersonalDataCipher;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('badge-printing')]
#[Group('phase-3')]
final class CredentialPresentationTokenForBadgePrintTest extends TestCase
{
    public function test_badge_print_must_use_presentation_token_scope_not_credential_scope(): void
    {
        $cipher = app(PersonalDataCipher::class);
        $token = 'PRESENTATION-TOKEN-VALUE-1234567890';
        $encrypted = $cipher->encrypt($token, '1:5:credential-presentation');

        $credential = new Credential([
            'tenant_id' => '1',
            'event_id' => '5',
            'presentation_token_ciphertext' => json_encode($encrypted, JSON_THROW_ON_ERROR),
            'key_id' => $encrypted['key_id'],
        ]);

        self::assertSame(
            $token,
            app(CredentialPresentationToken::class)->resolve($credential),
        );

        $this->expectException(InvalidArgumentException::class);
        $cipher->decrypt(
            [
                'key_id' => $encrypted['key_id'],
                'ciphertext' => $credential->presentation_token_ciphertext,
            ],
            '1:5:credential',
        );
    }
}
