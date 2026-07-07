<?php

namespace Tests\Unit\AccessControl;

use App\Modules\AccessControl\Application\Support\AntiPassbackService;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\AccessEvent;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\AntiPassbackState;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\CreatesPhase2ScanFixture;
use Tests\Support\CreatesPhase4AcsFixture;
use Tests\Support\Phase4MySqlTestCase;

#[Group('phase-4')]
#[Group('acs-anti-passback')]
final class AntiPassbackServiceTest extends Phase4MySqlTestCase
{
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use CreatesPhase4AcsFixture;
    use DatabaseTransactions;
    use DatabaseTransactions;

    public function test_is_inside_reads_materialized_state(): void
    {
        $this->assertMySqlConnectionIsAvailable();

        $scan = $this->createIssuedCredentialScanFixture();
        $acs = $this->createAcsAuthorizationFixture($scan);
        $service = app(AntiPassbackService::class);

        self::assertFalse($service->isInside(
            $acs['event']->tenant_id,
            $acs['event']->id,
            $acs['credential']->id,
            $acs['zone']->id,
        ));

        AntiPassbackState::query()->create([
            'id' => (string) Str::ulid(),
            'tenant_id' => $acs['event']->tenant_id,
            'event_id' => $acs['event']->id,
            'credential_id' => $acs['credential']->id,
            'zone_id' => $acs['zone']->id,
            'state' => 'inside',
        ]);

        self::assertTrue($service->isInside(
            $acs['event']->tenant_id,
            $acs['event']->id,
            $acs['credential']->id,
            $acs['zone']->id,
        ));
    }

    public function test_apply_event_sets_state_and_ignores_out_of_order_events(): void
    {
        $this->assertMySqlConnectionIsAvailable();

        $scan = $this->createIssuedCredentialScanFixture();
        $acs = $this->createAcsAuthorizationFixture($scan);
        $service = app(AntiPassbackService::class);
        $newer = now();
        $older = now()->subHour();

        $entry = AccessEvent::factory()->create([
            'tenant_id' => $acs['event']->tenant_id,
            'event_id' => $acs['event']->id,
            'event_type' => 'entry',
            'credential_id' => $acs['credential']->id,
            'zone_id' => $acs['zone']->id,
            'lane_id' => $acs['lane']->id,
            'direction' => 'entry',
            'occurred_at' => $newer,
        ]);

        $service->applyEvent($entry);

        $state = AntiPassbackState::query()->where('credential_id', $acs['credential']->id)->firstOrFail();
        self::assertSame('inside', $state->state);

        $exit = AccessEvent::factory()->create([
            'tenant_id' => $acs['event']->tenant_id,
            'event_id' => $acs['event']->id,
            'event_type' => 'exit',
            'credential_id' => $acs['credential']->id,
            'zone_id' => $acs['zone']->id,
            'lane_id' => $acs['lane']->id,
            'direction' => 'exit',
            'occurred_at' => $newer->copy()->addMinute(),
        ]);

        $service->applyEvent($exit);
        $state->refresh();
        self::assertSame('outside', $state->state);

        $staleEntry = AccessEvent::factory()->create([
            'tenant_id' => $acs['event']->tenant_id,
            'event_id' => $acs['event']->id,
            'event_type' => 'entry',
            'credential_id' => $acs['credential']->id,
            'zone_id' => $acs['zone']->id,
            'lane_id' => $acs['lane']->id,
            'direction' => 'entry',
            'occurred_at' => $older,
        ]);

        $service->applyEvent($staleEntry);
        $state->refresh();
        self::assertSame('outside', $state->state);
    }
}
