<?php

namespace Tests\Unit\VenueMarketplace;

use App\Modules\VenueMarketplace\Domain\Exceptions\MarketplaceDomainException;
use App\Modules\VenueMarketplace\Domain\Services\MarketplaceDisputeStateMachine;
use App\Modules\VenueMarketplace\Http\Problems\Phase6Problem;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MarketplaceDisputeStateMachineTest extends TestCase
{
    private MarketplaceDisputeStateMachine $stateMachine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stateMachine = new MarketplaceDisputeStateMachine;
    }

    #[DataProvider('allowedTransitions')]
    public function test_allowed_transitions_enforce_platform_actor(
        string $current,
        string $target,
        string $actorScope,
    ): void {
        self::assertSame(
            $target,
            $this->stateMachine->transition($current, $target, $actorScope),
        );
    }

    public static function allowedTransitions(): array
    {
        return [
            'platform moves open to under_review' => ['open', 'under_review', 'platform'],
            'platform resolves open dispute' => ['open', 'resolved', 'platform'],
            'platform rejects open dispute' => ['open', 'rejected', 'platform'],
            'platform resolves under_review dispute' => ['under_review', 'resolved', 'platform'],
            'platform rejects under_review dispute' => ['under_review', 'rejected', 'platform'],
        ];
    }

    #[DataProvider('illegalTransitions')]
    public function test_illegal_transitions_fail_before_persistence(
        string $current,
        string $target,
        string $actorScope,
    ): void {
        $this->assertDisputeConflict(
            fn () => $this->stateMachine->transition($current, $target, $actorScope),
        );
    }

    public static function illegalTransitions(): array
    {
        return [
            'owner cannot move to under_review' => ['open', 'under_review', 'owner'],
            'organizer cannot move to under_review' => ['open', 'under_review', 'organizer'],
            'owner cannot resolve' => ['open', 'resolved', 'owner'],
            'organizer cannot resolve' => ['under_review', 'resolved', 'organizer'],
            'owner cannot reject' => ['open', 'rejected', 'owner'],
            'system cannot resolve' => ['open', 'resolved', 'system'],
            'unknown scope cannot transition' => ['open', 'under_review', 'admin'],
        ];
    }

    #[DataProvider('terminalStatuses')]
    public function test_terminal_statuses_cannot_transition(string $terminal): void
    {
        $this->assertDisputeConflict(
            fn () => $this->stateMachine->transition($terminal, 'open', 'platform'),
        );
        $this->assertDisputeConflict(
            fn () => $this->stateMachine->transition($terminal, 'under_review', 'platform'),
        );
    }

    public static function terminalStatuses(): array
    {
        return [
            'resolved' => ['resolved'],
            'rejected' => ['rejected'],
        ];
    }

    public function test_can_transition_predicate_matches_transition_behavior(): void
    {
        self::assertTrue($this->stateMachine->canTransition('open', 'under_review', 'platform'));
        self::assertTrue($this->stateMachine->canTransition('open', 'resolved', 'platform'));
        self::assertFalse($this->stateMachine->canTransition('open', 'under_review', 'owner'));
        self::assertFalse($this->stateMachine->canTransition('resolved', 'open', 'platform'));
        self::assertFalse($this->stateMachine->canTransition('rejected', 'under_review', 'platform'));
    }

    public function test_reason_validation_rejects_empty_and_oversized_values(): void
    {
        $this->stateMachine->assertValidReason('Valid dispute reason');
        self::assertTrue(true);

        foreach (['', '   ', str_repeat('x', 4001)] as $invalid) {
            $this->assertDisputeConflict(
                fn () => $this->stateMachine->assertValidReason($invalid),
            );
        }
    }

    public function test_reason_code_validation_rejects_empty_and_oversized_values(): void
    {
        $this->stateMachine->assertValidReasonCode('billing_error');
        self::assertTrue(true);

        foreach (['', '   ', str_repeat('x', 81)] as $invalid) {
            $this->assertDisputeConflict(
                fn () => $this->stateMachine->assertValidReasonCode($invalid),
            );
        }
    }

    public function test_note_validation_rejects_empty_and_oversized_values(): void
    {
        $this->stateMachine->assertValidNote('This is a valid note.');
        self::assertTrue(true);

        foreach (['', '   ', str_repeat('x', 4001)] as $invalid) {
            $this->assertDisputeConflict(
                fn () => $this->stateMachine->assertValidNote($invalid),
            );
        }
    }

    public function test_visibility_validation_accepts_only_known_values(): void
    {
        $this->stateMachine->assertValidVisibility('participants');
        $this->stateMachine->assertValidVisibility('platform_only');
        self::assertTrue(true);

        foreach (['public', 'private', 'internal', ''] as $invalid) {
            $this->assertDisputeConflict(
                fn () => $this->stateMachine->assertValidVisibility($invalid),
            );
        }
    }

    private function assertDisputeConflict(callable $operation): void
    {
        try {
            $operation();
            self::fail('Expected dispute transition to be denied.');
        } catch (MarketplaceDomainException $exception) {
            self::assertSame(Phase6Problem::MARKETPLACE_DISPUTE_STATE_CONFLICT, $exception->reasonCode);
        }
    }
}
