<?php

namespace Tests\Integration\Security;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\Phase2MySqlTestCase;

#[Group('phase-2')]
#[Group('phase-2-isolation')]
#[Group('wallet-passes')]
final class WalletPassIsolationTest extends Phase2MySqlTestCase
{
    use CreatesPhase1RegistrationFixture;
    use DatabaseTransactions;

    public function test_cross_tenant_and_cross_event_wallet_requests_match_unknown_reference_responses(): void
    {
        $fixtureA = $this->createRegistrationFixture(domainReference: 'tenant-a.example.test');
        $fixtureB = $this->createRegistrationFixture(domainReference: 'tenant-b.example.test');

        $created = $this->withHeader('Idempotency-Key', 'wallet-isolation')
            ->postJson("http://tenant-a.example.test/api/v1/public/events/{$fixtureA['event']->slug}/registrations", $this->registrationPayload($fixtureA))
            ->assertCreated();

        $reference = $created->json('data.public_reference');
        $token = $created->json('data.access_token');

        $unknown = $this->withHeader('X-Order-Access-Token', 'wrong')
            ->getJson('http://tenant-a.example.test/api/v1/public/orders/ord_unknown/wallet-passes/apple');

        $wrongHost = $this->withHeader('X-Order-Access-Token', $token)
            ->getJson("http://tenant-b.example.test/api/v1/public/orders/{$reference}/wallet-passes/apple");

        $wrongToken = $this->withHeader('X-Order-Access-Token', 'wrong')
            ->getJson("http://tenant-a.example.test/api/v1/public/orders/{$reference}/wallet-passes/google");

        foreach ([$unknown, $wrongHost, $wrongToken] as $response) {
            $response->assertNotFound()->assertJsonPath('code', 'resource_not_found');
            self::assertSame($unknown->json('detail'), $response->json('detail'));
        }
    }
}
