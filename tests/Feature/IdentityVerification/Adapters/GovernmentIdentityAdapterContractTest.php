<?php

namespace Tests\Feature\IdentityVerification\Adapters;

use App\Modules\IdentityVerification\Domain\ValueObjects\GovernmentIdentityContext;
use App\Modules\IdentityVerification\Infrastructure\Adapters\MockGovernmentIdentityAdapter;
use App\Modules\IdentityVerification\Testing\FakeGovernmentIdentityAdapter;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('phase-5')]
#[Group('identity-adapters')]
final class GovernmentIdentityAdapterContractTest extends TestCase
{
    public function test_mock_government_adapter_implements_contract_shape(): void
    {
        $adapter = new MockGovernmentIdentityAdapter;
        $context = new GovernmentIdentityContext('1', '10', '100', 'idem-1');

        $start = $adapter->startVerification($context);
        self::assertSame('started', $start->status);
        self::assertNotNull($start->reference);

        $callback = $adapter->handleCallback(['status' => 'verified', 'reference' => 'ref-1']);
        self::assertSame('verified', $callback->status);
        self::assertSame('ref-1', $callback->reference);

        $result = $adapter->fetchResult('ref-1');
        self::assertSame('verified', $result->status);
        self::assertSame('Mock Verified Attendee', $result->attributes?->verifiedName);
    }

    public function test_fake_government_adapter_is_deterministic_and_records_calls(): void
    {
        $adapter = new FakeGovernmentIdentityAdapter;
        $context = new GovernmentIdentityContext('2', '20', '200', 'idem-2');

        $adapter->startVerification($context);
        $adapter->handleCallback(['status' => 'verified', 'reference' => 'ref-2']);
        $adapter->fetchResult('ref-2');
        $adapter->mapAttributes(['verified_name' => 'Jane Doe', 'verified_nationality' => 'SA']);

        $calls = $adapter->calls();
        self::assertCount(4, $calls);
        self::assertSame('startVerification', $calls[0]['operation']);
        self::assertSame('handleCallback', $calls[1]['operation']);
        self::assertSame('fetchResult', $calls[2]['operation']);
        self::assertSame('mapAttributes', $calls[3]['operation']);
    }
}
