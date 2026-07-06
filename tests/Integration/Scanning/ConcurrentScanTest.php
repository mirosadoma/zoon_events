<?php

namespace Tests\Integration\Scanning;

use App\Modules\Scanning\Application\Actions\SubmitScanAction;
use App\Modules\Scanning\Domain\ValueObjects\ScanContext;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\BuildsTenantFixtures;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\CreatesPhase2ScanFixture;
use Tests\Support\Phase2MySqlTestCase;

#[Group('phase-2')]
#[Group('check-in')]
final class ConcurrentScanTest extends Phase2MySqlTestCase
{
    use BuildsTenantFixtures;
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use DatabaseTransactions;

    public function test_rapid_duplicate_scans_produce_one_accepted_and_one_duplicate(): void
    {
        $scan = $this->createIssuedCredentialScanFixture();
        app(TenantContextStore::class)->bind(
            $scan['fixture']['tenant'],
            $scan['membership'],
            $scan['scanner'],
        );
        $action = app(SubmitScanAction::class);
        $context = new ScanContext(
            tenantId: $scan['fixture']['tenant']->id,
            eventId: $scan['fixture']['event']->id,
            scannerId: $scan['scanner']->id,
            scannerType: 'staff_phone',
            qrPayload: $scan['token'],
        );

        $first = $action->execute($context);
        $second = $action->execute($context);

        self::assertSame('accepted', $first->decision->result);
        self::assertSame('duplicate', $second->decision->result);
    }
}
