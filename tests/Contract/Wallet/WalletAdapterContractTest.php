<?php

namespace Tests\Contract\Wallet;

use App\Modules\WalletPasses\Contracts\WalletAdapter;
use App\Modules\WalletPasses\Domain\ValueObjects\WalletPassGenerationRequest;
use App\Modules\WalletPasses\Domain\ValueObjects\WalletPassRevocationRequest;
use App\Modules\WalletPasses\Domain\ValueObjects\WalletPassUpdateRequest;
use App\Modules\WalletPasses\Infrastructure\Adapters\Apple\ApplePassBuilder;
use App\Modules\WalletPasses\Infrastructure\Adapters\Apple\AppleWalletAdapter;
use App\Modules\WalletPasses\Infrastructure\Adapters\Google\GoogleWalletAdapter;
use App\Modules\WalletPasses\Infrastructure\Adapters\Google\GoogleWalletObjectBuilder;
use App\Modules\WalletPasses\Infrastructure\Secrets\EnvironmentWalletSecretLoader;
use App\Modules\WalletPasses\Testing\FakeWalletAdapter;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('phase-2')]
#[Group('wallet-passes')]
final class WalletAdapterContractTest extends TestCase
{
    public function test_fake_adapter_satisfies_the_contract_test_matrix(): void
    {
        $this->assertWalletAdapterContract(new FakeWalletAdapter, 'apple');
        $this->assertWalletAdapterContract(new FakeWalletAdapter, 'google');
    }

    #[Group('wallet-passes')]
    public function test_apple_adapter_satisfies_the_contract_test_matrix(): void
    {
        $adapter = new AppleWalletAdapter(new ApplePassBuilder(new EnvironmentWalletSecretLoader));
        $this->assertWalletAdapterContract($adapter, 'apple');
    }

    #[Group('wallet-passes')]
    public function test_google_adapter_satisfies_the_contract_test_matrix(): void
    {
        $adapter = new GoogleWalletAdapter(new GoogleWalletObjectBuilder(new EnvironmentWalletSecretLoader));
        $this->assertWalletAdapterContract($adapter, 'google');
    }

    private function assertWalletAdapterContract(WalletAdapter $adapter, string $provider): void
    {
        $serial = '01SYNTHETICSERIAL0000000001';

        $inactive = $adapter->generate($this->generationRequest($provider, $serial, 'revoked'));
        self::assertSame('failed', $inactive->status);
        self::assertSame('credential_not_active', $inactive->reasonCode);

        $created = $adapter->generate($this->generationRequest($provider, $serial, 'active', 'zt1.synthetic-token'));
        self::assertSame('created', $created->status);
        self::assertNotEmpty($created->passUrl);
        self::assertStringNotContainsString('secret', mb_strtolower((string) $created->passUrl));

        $updated = $adapter->update(new WalletPassUpdateRequest(
            '01SYNTHETICTENANT000000000',
            '01SYNTHETICEVENT0000000000',
            $serial,
            $provider,
            'active',
        ));
        self::assertContains($updated->status, ['updated', 'unavailable', 'failed']);

        $revoked = $adapter->revoke(new WalletPassRevocationRequest(
            '01SYNTHETICTENANT000000000',
            '01SYNTHETICEVENT0000000000',
            $serial,
            $provider,
            'credential_revoked',
        ));
        self::assertContains($revoked->status, ['revoked', 'unavailable', 'failed']);
        self::assertSame($revoked->status, $adapter->revoke(new WalletPassRevocationRequest(
            '01SYNTHETICTENANT000000000',
            '01SYNTHETICEVENT0000000000',
            $serial,
            $provider,
            'credential_revoked',
        ))->status);

        $unknown = $adapter->update(new WalletPassUpdateRequest(
            '01SYNTHETICTENANT000000000',
            '01SYNTHETICEVENT0000000000',
            '01UNKNOWNPASS000000000000',
            $provider,
            'active',
        ));
        self::assertContains($unknown->status, ['failed', 'unavailable']);
        self::assertStringNotContainsString('tenant', mb_strtolower((string) $unknown->reasonCode));
    }

    private function generationRequest(
        string $provider,
        string $serial,
        string $credentialStatus = 'active',
        ?string $credentialToken = 'zt1.synthetic-token',
    ): WalletPassGenerationRequest {
        return new WalletPassGenerationRequest(
            tenantId: '01SYNTHETICTENANT000000000',
            eventId: '01SYNTHETICEVENT0000000000',
            attendeeId: '01SYNTHETICATTENDEE00000000',
            credentialId: '01SYNTHETICCREDENTIAL000000',
            credentialStatus: $credentialStatus,
            provider: $provider,
            passSerialNumber: $serial,
            locale: 'en',
            credentialToken: $credentialToken,
            eventName: 'Synthetic Summit',
            eventDate: '2027-01-10T12:00:00Z',
            eventLocation: 'Riyadh',
            attendeeName: 'Synthetic Attendee',
            ticketTypeLabel: 'General Admission',
        );
    }
}
