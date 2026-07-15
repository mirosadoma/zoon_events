<?php

namespace Tests\Unit\VenueMarketplace;

use App\Modules\VenueMarketplace\Domain\Exceptions\MarketplaceDomainException;
use App\Modules\VenueMarketplace\Domain\Services\RentalStateMachine;
use App\Modules\VenueMarketplace\Http\Problems\Phase6Problem;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RentalStateMachineTest extends TestCase
{
    private RentalStateMachine $stateMachine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stateMachine = new RentalStateMachine;
    }

    #[DataProvider('allowedTransitions')]
    public function test_allowed_transitions_enforce_the_authoritative_actor(
        string $current,
        string $target,
        string $actor,
        ?string $reason,
    ): void {
        $transitionedAt = new DateTimeImmutable('2026-07-14T12:00:00.123456Z');

        self::assertSame(
            $target,
            $this->stateMachine->transition(
                $current,
                $target,
                $actor,
                $reason,
                expectedVersion: 7,
                currentVersion: 7,
                transitionedAt: $transitionedAt,
            ),
        );
    }

    public static function allowedTransitions(): array
    {
        return [
            'owner approves requested rental' => ['requested', 'approved', 'owner', null],
            'owner rejects requested rental with reason' => ['requested', 'rejected', 'owner', 'asset_unavailable'],
            'organizer cancels requested rental' => ['requested', 'cancelled', 'organizer', null],
            'organizer cancels approved rental before activation' => ['approved', 'cancelled', 'organizer', null],
            'owner revokes approved rental with reason' => ['approved', 'revoked', 'owner', 'venue_unavailable'],
            'system activates approved rental' => ['approved', 'active', 'system', null],
            'system completes active rental' => ['active', 'completed', 'system', null],
            'owner revokes active rental with reason' => ['active', 'revoked', 'owner', 'safety_shutdown'],
        ];
    }

    #[DataProvider('illegalTransitions')]
    public function test_impossible_or_wrong_actor_transitions_fail_before_persistence(
        string $current,
        string $target,
        string $actor,
        ?string $reason = null,
    ): void {
        $this->assertStateConflict(fn () => $this->stateMachine->transition(
            $current,
            $target,
            $actor,
            $reason,
            expectedVersion: 1,
            currentVersion: 1,
            transitionedAt: new DateTimeImmutable('2026-07-14T12:00:00Z'),
        ));
    }

    public static function illegalTransitions(): array
    {
        return [
            'organizer cannot approve' => ['requested', 'approved', 'organizer'],
            'organizer cannot reject' => ['requested', 'rejected', 'organizer', 'declined'],
            'owner cannot cancel' => ['requested', 'cancelled', 'owner'],
            'organizer cannot revoke' => ['approved', 'revoked', 'organizer', 'declined'],
            'owner cannot activate' => ['approved', 'active', 'owner'],
            'system cannot reject' => ['requested', 'rejected', 'system', 'declined'],
            'requested cannot complete' => ['requested', 'completed', 'system'],
            'active cannot cancel' => ['active', 'cancelled', 'organizer'],
            'approved cannot complete directly' => ['approved', 'completed', 'system'],
            'unknown current status fails closed' => ['submitted', 'approved', 'owner'],
            'unknown target status fails closed' => ['requested', 'pending', 'owner'],
        ];
    }

    #[DataProvider('terminalStatuses')]
    public function test_terminal_statuses_cannot_transition(string $terminal): void
    {
        $this->assertStateConflict(fn () => $this->stateMachine->transition(
            $terminal,
            'approved',
            'owner',
            'retry',
            expectedVersion: 2,
            currentVersion: 2,
            transitionedAt: new DateTimeImmutable('2026-07-14T12:00:00Z'),
        ));
    }

    public static function terminalStatuses(): array
    {
        return array_map(
            static fn (string $status): array => [$status],
            ['rejected', 'cancelled', 'revoked', 'completed'],
        );
    }

    public function test_reject_and_revoke_require_a_bounded_non_blank_reason(): void
    {
        foreach ([
            ['requested', 'rejected', null],
            ['requested', 'rejected', '   '],
            ['approved', 'revoked', null],
            ['active', 'revoked', str_repeat('x', 2001)],
        ] as [$current, $target, $reason]) {
            $this->assertStateConflict(fn () => $this->stateMachine->transition(
                $current,
                $target,
                'owner',
                $reason,
                expectedVersion: 3,
                currentVersion: 3,
                transitionedAt: new DateTimeImmutable('2026-07-14T12:00:00Z'),
            ));
        }
    }

    public function test_stale_and_non_positive_versions_fail_closed(): void
    {
        $this->assertStateConflict(fn () => $this->stateMachine->transition(
            'requested',
            'approved',
            'owner',
            null,
            expectedVersion: 4,
            currentVersion: 5,
            transitionedAt: new DateTimeImmutable('2026-07-14T12:00:00Z'),
        ));

        $this->assertStateConflict(fn () => $this->stateMachine->transition(
            'requested',
            'approved',
            'owner',
            null,
            expectedVersion: 0,
            currentVersion: 0,
            transitionedAt: new DateTimeImmutable('2026-07-14T12:00:00Z'),
        ));
    }

    private function assertStateConflict(callable $operation): void
    {
        try {
            $operation();
            self::fail('Expected rental transition to be denied.');
        } catch (MarketplaceDomainException $exception) {
            self::assertSame(Phase6Problem::MARKETPLACE_RENTAL_STATE_CONFLICT, $exception->reasonCode);
        }
    }
}
