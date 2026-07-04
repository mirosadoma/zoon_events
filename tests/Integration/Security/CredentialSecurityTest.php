<?php

namespace Tests\Integration\Security;

use App\Exceptions\FoundationException;
use App\Modules\Credentials\Application\Validation\CredentialValidator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\Phase1MySqlTestCase;

#[Group('phase-1')]
#[Group('credential-security')]
final class CredentialSecurityTest extends Phase1MySqlTestCase
{
    use CreatesPhase1RegistrationFixture;
    use DatabaseTransactions;

    public function test_tamper_and_cross_tenant_validation_fail_with_same_safe_code(): void
    {
        $fixture = $this->createRegistrationFixture();
        $created = $this->withHeader('Idempotency-Key', 'credential-security')->postJson(
            "http://register.example.test/api/v1/public/events/{$fixture['event']->slug}/registrations",
            $this->registrationPayload($fixture),
        )->assertCreated();
        $token = $created->json('data.credential.qr_payload');
        $other = $this->createRegistrationFixture();
        $codes = [];
        foreach ([substr($token, 0, -1).'x', $token] as $index => $candidate) {
            try {
                app(CredentialValidator::class)->validate(
                    $candidate,
                    $index === 0 ? $fixture['tenant']->id : $other['tenant']->id,
                );
            } catch (FoundationException $exception) {
                $codes[] = $exception->problemCode;
            }
        }
        self::assertSame(['credential_invalid', 'credential_invalid'], $codes);
    }
}
