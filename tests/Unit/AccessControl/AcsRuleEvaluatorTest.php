<?php

namespace Tests\Unit\AccessControl;

use App\Modules\AccessControl\Application\Support\AcsRuleEvaluator;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsAuthorizationRule;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsLane;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsZone;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\MySqlTestCase;

#[Group('phase-4')]
#[Group('acs-authorization')]
final class AcsRuleEvaluatorTest extends MySqlTestCase
{
    use CreatesPhase1RegistrationFixture;
    use DatabaseTransactions;

    /** @return array{zone:AcsZone,lane:AcsLane,tenant_id:string,event_id:string} */
    private function createZoneLaneFixture(): array
    {
        $fixture = $this->createRegistrationFixture();
        $zone = AcsZone::factory()->create([
            'tenant_id' => $fixture['tenant']->id,
            'event_id' => $fixture['event']->id,
        ]);
        $lane = AcsLane::factory()->create([
            'tenant_id' => $fixture['tenant']->id,
            'event_id' => $fixture['event']->id,
            'zone_id' => $zone->id,
        ]);

        return [
            'zone' => $zone,
            'lane' => $lane,
            'tenant_id' => $fixture['tenant']->id,
            'event_id' => $fixture['event']->id,
        ];
    }

    public function test_returns_null_when_an_active_rule_permits_the_tuple(): void
    {
        $this->assertMySqlConnectionIsAvailable();
        $context = $this->createZoneLaneFixture();

        AcsAuthorizationRule::factory()->create([
            'tenant_id' => $context['tenant_id'],
            'event_id' => $context['event_id'],
            'zone_id' => $context['zone']->id,
            'ticket_type_id' => null,
            'lane_id' => $context['lane']->id,
            'access_direction' => 'entry',
        ]);

        $result = app(AcsRuleEvaluator::class)->evaluate(
            $context['tenant_id'],
            $context['event_id'],
            'any-ticket-type',
            null,
            $context['zone']->id,
            $context['lane']->id,
            'entry',
            now(),
        );

        self::assertNull($result);
    }

    public function test_returns_zone_not_permitted_when_no_active_rule_matches_the_zone(): void
    {
        $this->assertMySqlConnectionIsAvailable();
        $context = $this->createZoneLaneFixture();

        $result = app(AcsRuleEvaluator::class)->evaluate(
            $context['tenant_id'],
            $context['event_id'],
            '01JTESTTICKETTYPE00000001',
            null,
            $context['zone']->id,
            $context['lane']->id,
            'entry',
            now(),
        );

        self::assertSame('zone_not_permitted', $result);
    }

    public function test_returns_lane_not_permitted_when_zone_matches_but_lane_does_not(): void
    {
        $this->assertMySqlConnectionIsAvailable();
        $context = $this->createZoneLaneFixture();
        $otherLane = AcsLane::factory()->create([
            'tenant_id' => $context['tenant_id'],
            'event_id' => $context['event_id'],
            'zone_id' => $context['zone']->id,
        ]);

        AcsAuthorizationRule::factory()->create([
            'tenant_id' => $context['tenant_id'],
            'event_id' => $context['event_id'],
            'zone_id' => $context['zone']->id,
            'ticket_type_id' => null,
            'lane_id' => $otherLane->id,
            'access_direction' => 'entry',
        ]);

        $result = app(AcsRuleEvaluator::class)->evaluate(
            $context['tenant_id'],
            $context['event_id'],
            '01JTESTTICKETTYPE00000001',
            null,
            $context['zone']->id,
            $context['lane']->id,
            'entry',
            now(),
        );

        self::assertSame('lane_not_permitted', $result);
    }

    public function test_returns_outside_time_window_when_rule_window_excludes_now(): void
    {
        $this->assertMySqlConnectionIsAvailable();
        $context = $this->createZoneLaneFixture();

        AcsAuthorizationRule::factory()->create([
            'tenant_id' => $context['tenant_id'],
            'event_id' => $context['event_id'],
            'zone_id' => $context['zone']->id,
            'ticket_type_id' => null,
            'lane_id' => $context['lane']->id,
            'access_direction' => 'entry',
            'valid_from' => now()->addHour(),
            'valid_until' => now()->addHours(2),
        ]);

        $result = app(AcsRuleEvaluator::class)->evaluate(
            $context['tenant_id'],
            $context['event_id'],
            '01JTESTTICKETTYPE00000001',
            null,
            $context['zone']->id,
            $context['lane']->id,
            'entry',
            now(),
        );

        self::assertSame('outside_time_window', $result);
    }
}
