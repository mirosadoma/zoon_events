<?php

namespace Tests\Feature\WalletPasses;

use App\Modules\WalletPasses\Infrastructure\Persistence\Models\WalletPass;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\Phase2MySqlTestCase;

#[Group('phase-2')]
#[Group('wallet-passes')]
final class AppleWebServiceTest extends Phase2MySqlTestCase
{
    use CreatesPhase1RegistrationFixture;
    use DatabaseTransactions;

    public function test_apple_web_service_endpoints_enforce_contract_status_codes(): void
    {
        $fixture = $this->createRegistrationFixture(domainReference: 'register.example.test');
        $created = $this->withHeader('Idempotency-Key', 'apple-web-service')
            ->postJson("http://register.example.test/api/v1/public/events/{$fixture['event']->slug}/registrations", $this->registrationPayload($fixture))
            ->assertCreated();

        $reference = $created->json('data.public_reference');
        $token = $created->json('data.access_token');
        $this->withHeader('X-Order-Access-Token', $token)
            ->get("http://register.example.test/api/v1/public/orders/{$reference}/wallet-passes/apple")
            ->assertOk();

        $pass = WalletPass::query()->where('provider', 'apple')->firstOrFail();
        $device = 'device-library-01';
        $passType = (string) config('wallet.apple.pass_type_identifier');
        $serial = $pass->pass_serial_number;
        $base = 'http://register.example.test/api/v1/wallet/apple/v1';
        $auth = 'ApplePass '.$pass->apple_authentication_token;

        $this->withHeader('Authorization', $auth)
            ->postJson("{$base}/devices/{$device}/registrations/{$passType}/{$serial}", [
                'pushToken' => 'apns-token-synthetic',
            ])->assertCreated();

        $this->withHeader('Authorization', $auth)
            ->postJson("{$base}/devices/{$device}/registrations/{$passType}/{$serial}", [
                'pushToken' => 'apns-token-synthetic',
            ])->assertOk();

        $this->getJson("{$base}/devices/{$device}/registrations/{$passType}")
            ->assertOk()
            ->assertJsonStructure(['serialNumbers', 'lastUpdated']);

        $this->withHeader('Authorization', $auth)
            ->get("{$base}/passes/{$passType}/{$serial}")
            ->assertOk();

        $this->withHeader('Authorization', $auth)
            ->deleteJson("{$base}/devices/{$device}/registrations/{$passType}/{$serial}")
            ->assertOk();
    }

    public function test_apple_web_service_rejects_invalid_authentication_tokens(): void
    {
        $fixture = $this->createRegistrationFixture(domainReference: 'register.example.test');
        $created = $this->withHeader('Idempotency-Key', 'apple-web-service-auth')
            ->postJson("http://register.example.test/api/v1/public/events/{$fixture['event']->slug}/registrations", $this->registrationPayload($fixture))
            ->assertCreated();

        $reference = $created->json('data.public_reference');
        $token = $created->json('data.access_token');
        $this->withHeader('X-Order-Access-Token', $token)
            ->get("http://register.example.test/api/v1/public/orders/{$reference}/wallet-passes/apple")
            ->assertOk();

        $pass = WalletPass::query()->where('provider', 'apple')->firstOrFail();
        $device = 'device-library-02';
        $passType = (string) config('wallet.apple.pass_type_identifier');
        $serial = $pass->pass_serial_number;
        $base = 'http://register.example.test/api/v1/wallet/apple/v1';

        $this->withHeader('Authorization', 'ApplePass invalid-token')
            ->postJson("{$base}/devices/{$device}/registrations/{$passType}/{$serial}", [
                'pushToken' => 'apns-token-synthetic',
            ])->assertUnauthorized();

        $this->withHeader('Authorization', 'ApplePass invalid-token')
            ->deleteJson("{$base}/devices/{$device}/registrations/{$passType}/{$serial}")
            ->assertUnauthorized();

        $this->withHeader('Authorization', 'ApplePass invalid-token')
            ->get("{$base}/passes/{$passType}/{$serial}")
            ->assertUnauthorized();
    }
}
