<?php

namespace Tests\Feature\AccessControl;

use App\Modules\AccessControl\Application\Actions\RegisterAcsIntegrationCredentialAction;
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
#[Group('acs-events')]
final class AccessEventCallbackApiTest extends Phase4MySqlTestCase
{
    use AssertsProblemDetails;
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use CreatesPhase4AcsFixture;
    use DatabaseTransactions;

    public function test_event_callback_records_access_events_idempotently(): void
    {
        $this->assertMySqlConnectionIsAvailable();

        $scan = $this->createIssuedCredentialScanFixture();
        $acs = $this->createAcsAuthorizationFixture($scan);
        $url = '/api/v1/acs/v1/events';
        $externalEventId = 'ext-event-'.Str::lower((string) Str::ulid());

        $this->postJson($url, [
            'external_event_id' => $externalEventId,
            'external_acs_lane_id' => $acs['lane']->external_acs_lane_id,
            'event_type' => 'entry',
            'occurred_at' => now()->toIso8601String(),
            'credential_reference' => $acs['token'],
        ])->assertUnauthorized();

        $first = $this->postJson($url, [
            'external_event_id' => $externalEventId,
            'external_acs_lane_id' => $acs['lane']->external_acs_lane_id,
            'event_type' => 'entry',
            'occurred_at' => now()->toIso8601String(),
            'credential_reference' => $acs['token'],
        ], $this->acsIntegrationHeaders($acs['secret'], 'acs-event-first-'.Str::ulid()));

        $first->assertAccepted()
            ->assertJsonStructure([
                'data' => ['id', 'event_type', 'direction', 'zone_id', 'lane_id', 'occurred_at'],
            ])
            ->assertJsonPath('data.event_type', 'entry')
            ->assertJsonPath('data.lane_id', $acs['lane']->id);

        $lane = AcsLane::query()->findOrFail($acs['lane']->id);
        self::assertNotNull($lane->last_seen_at);

        $second = $this->postJson($url, [
            'external_event_id' => $externalEventId,
            'external_acs_lane_id' => $acs['lane']->external_acs_lane_id,
            'event_type' => 'entry',
            'occurred_at' => now()->toIso8601String(),
            'credential_reference' => $acs['token'],
        ], $this->acsIntegrationHeaders($acs['secret'], 'acs-event-second-'.Str::ulid()));

        $second->assertAccepted()
            ->assertJsonPath('data.id', $first->json('data.id'));

        self::assertSame(1, AccessEvent::query()
            ->where('tenant_id', $acs['event']->tenant_id)
            ->where('external_event_id', $externalEventId)
            ->count());
    }

    public function test_event_callback_rejects_out_of_scope_lane_and_missing_capability(): void
    {
        $this->assertMySqlConnectionIsAvailable();

        $scan = $this->createAcsAuthorizationFixture($this->createIssuedCredentialScanFixture());
        $otherScan = $this->createIssuedCredentialScanFixture();
        $foreignAcs = $this->createAcsAuthorizationFixture($otherScan);
        $url = '/api/v1/acs/v1/events';

        $this->assertProblemDetails(
            $this->postJson($url, [
                'external_event_id' => 'foreign-event-'.Str::lower((string) Str::ulid()),
                'external_acs_lane_id' => $foreignAcs['lane']->external_acs_lane_id,
                'event_type' => 'entry',
                'occurred_at' => now()->toIso8601String(),
            ], $this->acsIntegrationHeaders($scan['secret'], 'acs-event-scope-'.Str::ulid())),
            404,
            'acs_event_out_of_scope',
        );

        $authorizeOnlySecret = app(RegisterAcsIntegrationCredentialAction::class)
            ->execute($scan['event']->tenant_id, $scan['event']->id, 'Authorize only', ['authorize'])['secret'];

        $this->assertProblemDetails(
            $this->postJson($url, [
                'external_event_id' => 'capability-event-'.Str::lower((string) Str::ulid()),
                'external_acs_lane_id' => $scan['lane']->external_acs_lane_id,
                'event_type' => 'entry',
                'occurred_at' => now()->toIso8601String(),
            ], $this->acsIntegrationHeaders($authorizeOnlySecret, 'acs-event-cap-'.Str::ulid())),
            403,
            'acs_capability_denied',
        );
    }
}
