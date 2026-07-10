<?php

namespace Tests\Feature\AccessControl;

use App\Modules\AccessControl\Infrastructure\Persistence\Models\AccessEvent;
use App\Modules\Scanning\Infrastructure\Persistence\Models\ScanEvent;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\CreatesPhase2ScanFixture;
use Tests\Support\CreatesPhase4AcsFixture;
use Tests\Support\Phase4MySqlTestCase;

#[Group('phase-4')]
#[Group('acs-authorization')]
final class AdmissionLaneCheckInTest extends Phase4MySqlTestCase
{
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use CreatesPhase4AcsFixture;
    use DatabaseTransactions;

    public function test_admission_lane_records_scan_event_and_non_admission_lane_does_not(): void
    {
        $this->assertMySqlConnectionIsAvailable();

        $scan = $this->createIssuedCredentialScanFixture();
        $admission = $this->createAcsAuthorizationFixture($scan, admissionLane: true);

        $this->postJson('/api/v1/acs/v1/authorize', [
            'external_acs_lane_id' => $admission['lane']->external_acs_lane_id,
            'direction' => 'entry',
            'credential_reference' => $admission['token'],
        ], $this->acsIntegrationHeaders($admission['secret'], 'acs-admission-entry-'.Str::ulid()))
            ->assertOk()
            ->assertJsonPath('data.decision', 'allow')
            ->assertJsonPath('data.scan_event_id', fn ($id) => $id !== null);

        $accessEventId = AccessEvent::query()->latest('created_at')->value('id');
        $scanEventId = AccessEvent::query()->findOrFail($accessEventId)->scan_event_id;

        self::assertNotNull($scanEventId);
        self::assertSame('acs_gate', ScanEvent::query()->findOrFail($scanEventId)->scanner_type);

        $admission['lane']->forceFill(['is_admission_lane' => false])->save();

        $this->postJson('/api/v1/acs/v1/authorize', [
            'external_acs_lane_id' => $admission['lane']->external_acs_lane_id,
            'direction' => 'entry',
            'credential_reference' => $admission['token'],
        ], $this->acsIntegrationHeaders($admission['secret'], 'acs-standard-entry-'.Str::ulid()))
            ->assertOk()
            ->assertJsonPath('data.scan_event_id', null);
    }
}
