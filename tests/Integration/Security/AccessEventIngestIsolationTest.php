<?php

namespace Tests\Integration\Security;

use App\Modules\AccessControl\Infrastructure\Persistence\Models\AccessEvent;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsLane;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\AssertsProblemDetails;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\CreatesPhase2ScanFixture;
use Tests\Support\CreatesPhase4AcsFixture;
use Tests\Support\Phase4MySqlTestCase;

#[Group('phase-4')]
#[Group('phase-4-isolation')]
#[Group('acs-events')]
final class AccessEventIngestIsolationTest extends Phase4MySqlTestCase
{
    use AssertsProblemDetails;
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use CreatesPhase4AcsFixture;
    use DatabaseTransactions;

    public function test_cross_scope_event_callback_is_rejected_and_creates_no_row(): void
    {
        $this->assertMySqlConnectionIsAvailable();

        $scan = $this->createIssuedCredentialScanFixture();
        $acs = $this->createAcsAuthorizationFixture($scan);
        $otherScan = $this->createIssuedCredentialScanFixture();
        $foreignAcs = $this->createAcsAuthorizationFixture($otherScan);
        $externalEventId = 'iso-event-'.Str::lower((string) Str::ulid());

        $this->assertProblemDetails(
            $this->postJson('/api/v1/acs/v1/events', [
                'external_event_id' => $externalEventId,
                'external_acs_lane_id' => $foreignAcs['lane']->external_acs_lane_id,
                'event_type' => 'entry',
                'occurred_at' => now()->toIso8601String(),
                'credential_reference' => $foreignAcs['token'],
            ], $this->acsIntegrationHeaders($acs['secret'], 'acs-ingest-iso-'.Str::ulid())),
            404,
            'acs_event_out_of_scope',
        );

        self::assertSame(0, AccessEvent::query()->where('external_event_id', $externalEventId)->count());
        self::assertNull(AcsLane::query()->find($foreignAcs['lane']->id)?->last_seen_at);
    }
}
