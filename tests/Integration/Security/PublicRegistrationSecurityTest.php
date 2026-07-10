<?php

namespace Tests\Integration\Security;

use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\Shared\Application\DataProtection\PersonalDataCipher;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\Phase1MySqlTestCase;

#[Group('phase-1')]
#[Group('phase-1-public-security')]
final class PublicRegistrationSecurityTest extends Phase1MySqlTestCase
{
    use CreatesPhase1RegistrationFixture;
    use DatabaseTransactions;

    public function test_host_scope_mass_assignment_and_xss_are_fail_closed_or_sanitized(): void
    {
        $fixture = $this->createRegistrationFixture();
        $payload = $this->registrationPayload($fixture);
        $payload['attendee']['first_name'] = '<script>alert(1)</script>Safe';
        $payload['attendee']['tenant_id'] = '01FORGEDTENANT000000000000';

        $this->withHeader('Idempotency-Key', 'mass-assignment')
            ->postJson("http://register.example.test/api/v1/public/events/{$fixture['event']->slug}/registrations", $payload)
            ->assertUnprocessable();

        unset($payload['attendee']['tenant_id']);
        $this->withHeader('Idempotency-Key', 'sanitized-xss')
            ->postJson("http://register.example.test/api/v1/public/events/{$fixture['event']->slug}/registrations", $payload)
            ->assertCreated();
        $attendee = Attendee::query()->where('tenant_id', $fixture['tenant']->id)->firstOrFail();
        $name = app(PersonalDataCipher::class)->decrypt(
            ['key_id' => $attendee->encryption_key_id, 'ciphertext' => $attendee->first_name_ciphertext],
            "{$fixture['tenant']->id}:{$fixture['event']->id}:attendee",
        );
        self::assertSame('alert(1)Safe', $name);

        $this->withHeader('Idempotency-Key', 'wrong-host')
            ->postJson("http://wrong.example.test/api/v1/public/events/{$fixture['event']->slug}/registrations", $this->registrationPayload($fixture))
            ->assertNotFound();
    }

    public function test_order_tokens_and_random_references_return_uniform_not_found(): void
    {
        $fixture = $this->createRegistrationFixture();
        $created = $this->withHeader('Idempotency-Key', 'token-test')
            ->postJson("http://register.example.test/api/v1/public/events/{$fixture['event']->slug}/registrations", $this->registrationPayload($fixture))
            ->assertCreated();
        $reference = $created->json('data.public_reference');

        $wrong = $this->withHeader('X-Order-Access-Token', 'wrong')
            ->getJson("http://register.example.test/api/v1/public/orders/{$reference}");
        $random = $this->withHeader('X-Order-Access-Token', 'wrong')
            ->getJson('http://register.example.test/api/v1/public/orders/ord_random');

        $wrong->assertNotFound()->assertJsonPath('code', 'resource_not_found');
        $random->assertNotFound()->assertJsonPath('code', 'resource_not_found');
        self::assertSame($wrong->json('detail'), $random->json('detail'));
    }
}
