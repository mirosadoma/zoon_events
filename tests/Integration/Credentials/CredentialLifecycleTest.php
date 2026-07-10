<?php

namespace Tests\Integration\Credentials;

use App\Exceptions\FoundationException;
use App\Modules\Credentials\Application\Actions\ReissueCredential;
use App\Modules\Credentials\Application\Actions\RevokeCredential;
use App\Modules\Credentials\Application\Validation\CredentialValidator;
use App\Modules\Credentials\Infrastructure\Persistence\Models\Credential;
use App\Modules\Tenancy\Domain\Context\TenantContext;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\TenantMembership;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\Phase1MySqlTestCase;

#[Group('phase-1')]
#[Group('credentials')]
final class CredentialLifecycleTest extends Phase1MySqlTestCase
{
    use CreatesPhase1RegistrationFixture;
    use DatabaseTransactions;

    public function test_revoke_is_immediate_and_reissue_leaves_exactly_one_active_token(): void
    {
        [$fixture, $token, $credential] = $this->issued();
        $context = $this->context($fixture);
        self::assertSame('active', app(CredentialValidator::class)->validate($token, $fixture['tenant']->id)['status']);

        app(RevokeCredential::class)->execute($context, $fixture['event']->id, $credential->id, 'Attendee request');
        try {
            app(CredentialValidator::class)->validate($token, $fixture['tenant']->id);
            self::fail('Revoked token must fail.');
        } catch (FoundationException $exception) {
            self::assertSame('credential_revoked', $exception->problemCode);
        }
        $replacement = app(ReissueCredential::class)->execute($context, $fixture['event']->id, $credential->id, 'Replacement');

        self::assertSame('active', app(CredentialValidator::class)->validate($replacement->token, $fixture['tenant']->id)['status']);
        self::assertSame(1, Credential::query()->where('attendee_id', $credential->attendee_id)->where('status', 'active')->count());
        self::assertSame('superseded', $credential->refresh()->status);
        self::assertSame($replacement->id, $credential->superseded_by_id);
    }

    /** @return array{array<string,mixed>,string,Credential} */
    private function issued(): array
    {
        $fixture = $this->createRegistrationFixture();
        $response = $this->withHeader('Idempotency-Key', 'credential-lifecycle')->postJson(
            "http://register.example.test/api/v1/public/events/{$fixture['event']->slug}/registrations",
            $this->registrationPayload($fixture),
        )->assertCreated();

        return [
            $fixture,
            $response->json('data.credential.qr_payload'),
            Credential::query()->where('event_id', $fixture['event']->id)->firstOrFail(),
        ];
    }

    private function context(array $fixture): TenantContext
    {
        $membership = TenantMembership::query()->create([
            'tenant_id' => $fixture['tenant']->id, 'user_id' => $fixture['actor']->id,
            'status' => 'active', 'created_by_user_id' => $fixture['actor']->id,
        ]);

        return new TenantContext($fixture['tenant'], $membership, $fixture['actor']);
    }
}
