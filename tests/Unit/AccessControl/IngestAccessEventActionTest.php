<?php

namespace Tests\Unit\AccessControl;

use App\Modules\AccessControl\Application\Actions\IngestAccessEventAction;
use App\Modules\AccessControl\Domain\ValueObjects\AcsIntegrationContext;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\AccessEvent;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsLane;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\CreatesPhase2ScanFixture;
use Tests\Support\CreatesPhase4AcsFixture;
use Tests\Support\Phase4MySqlTestCase;

#[Group('phase-4')]
#[Group('acs-events')]
final class IngestAccessEventActionTest extends Phase4MySqlTestCase
{
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use CreatesPhase4AcsFixture;
    use DatabaseTransactions;

    public function test_ingest_creates_one_row_and_is_idempotent(): void
    {
        $this->assertMySqlConnectionIsAvailable();

        $scan = $this->createIssuedCredentialScanFixture();
        $acs = $this->createAcsAuthorizationFixture($scan);
        $ctx = new AcsIntegrationContext($acs['event']->tenant_id, $acs['event']->id, ['event.ingest']);
        $externalEventId = 'unit-event-'.Str::lower((string) Str::ulid());
        $occurredAt = now()->subMinute();

        $action = app(IngestAccessEventAction::class);
        $first = $action->execute(
            $ctx,
            $externalEventId,
            $acs['lane']->external_acs_lane_id,
            'entry',
            $occurredAt,
            $acs['token'],
        );

        self::assertSame('entry', $first->event_type);
        self::assertNull($first->reason_code);
        self::assertSame($acs['lane']->id, $first->lane_id);
        self::assertSame($acs['zone']->id, $first->zone_id);

        $second = $action->execute(
            $ctx,
            $externalEventId,
            $acs['lane']->external_acs_lane_id,
            'entry',
            now(),
            $acs['token'],
        );

        self::assertSame($first->id, $second->id);
        self::assertSame(1, AccessEvent::query()->where('external_event_id', $externalEventId)->count());
    }

    public function test_ingest_updates_lane_last_seen_at_when_event_is_newer(): void
    {
        $this->assertMySqlConnectionIsAvailable();

        $scan = $this->createIssuedCredentialScanFixture();
        $acs = $this->createAcsAuthorizationFixture($scan);
        $ctx = new AcsIntegrationContext($acs['event']->tenant_id, $acs['event']->id, ['event.ingest']);
        $older = now()->subHours(2);
        $newer = now()->subHour();

        $acs['lane']->forceFill(['last_seen_at' => $older])->save();

        app(IngestAccessEventAction::class)->execute(
            $ctx,
            'seen-older-'.Str::lower((string) Str::ulid()),
            $acs['lane']->external_acs_lane_id,
            'exit',
            $newer,
        );

        $lane = AcsLane::query()->findOrFail($acs['lane']->id);
        self::assertSame($newer->getTimestamp(), $lane->last_seen_at->getTimestamp());

        app(IngestAccessEventAction::class)->execute(
            $ctx,
            'seen-stale-'.Str::lower((string) Str::ulid()),
            $acs['lane']->external_acs_lane_id,
            'exit',
            $older,
        );

        $lane->refresh();
        self::assertSame($newer->getTimestamp(), $lane->last_seen_at->getTimestamp());
    }
}
