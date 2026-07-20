<?php

namespace Tests\Feature\VenueMarketplace;

use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use App\Modules\VenueMarketplace\Application\Actions\ApproveRentalAction;
use App\Modules\VenueMarketplace\Application\Actions\CancelRentalAction;
use App\Modules\VenueMarketplace\Application\Actions\GenerateSettlementStatementAction;
use App\Modules\VenueMarketplace\Application\Actions\OpenMarketplaceDisputeAction;
use App\Modules\VenueMarketplace\Application\Actions\ReviseSettlementStatementAction;
use App\Modules\VenueMarketplace\Application\Queries\GetParticipantStatementQuery;
use App\Modules\VenueMarketplace\Domain\Exceptions\MarketplaceDomainException;
use App\Modules\VenueMarketplace\Http\Problems\Phase6Problem;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\SettlementStatement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesMarketplaceFixture;
use Tests\TestCase;

final class ReviseSettlementStatementTest extends TestCase
{
    use CreatesMarketplaceFixture;
    use RefreshDatabase;

    public function test_platform_can_create_linked_revision_with_factual_corrections(): void
    {
        [$owner, $organizer, $statement, $dispute, $platformUserId] = $this->disputedStatement('revise-basic');

        $originalLine = $statement->lines->first();
        $revision = app(ReviseSettlementStatementAction::class)->execute(
            $platformUserId,
            $statement->public_id,
            $dispute->public_id,
            'factual_correction',
            [[
                'asset_public_id' => $originalLine->asset_public_id,
                'unit_price_minor' => 800,
                'billable_units' => (int) $originalLine->billable_units,
            ]],
            'revise-basic-idempotency',
            'revise-basic-correlation',
        );

        self::assertSame(2, (int) $revision->revision);
        self::assertSame('issued', $revision->status);
        self::assertNotSame($statement->public_id, $revision->public_id);
        self::assertSame($statement->rental_outcome, $revision->rental_outcome);
        self::assertSame($statement->currency, $revision->currency);
        self::assertSame(
            $statement->agreed_start_at->toISOString(),
            $revision->agreed_start_at->toISOString(),
        );
        self::assertSame(
            $statement->agreed_end_at->toISOString(),
            $revision->agreed_end_at->toISOString(),
        );
    }

    public function test_revision_requires_reason_code(): void
    {
        [$owner, $organizer, $statement, $dispute, $platformUserId] = $this->disputedStatement('revise-reason');

        try {
            app(ReviseSettlementStatementAction::class)->execute(
                $platformUserId,
                $statement->public_id,
                $dispute->public_id,
                '',
                [],
                'revise-reason-idempotency',
                'revise-reason-correlation',
            );
            self::fail('Expected empty reason code to be rejected.');
        } catch (MarketplaceDomainException $exception) {
            self::assertSame(Phase6Problem::MARKETPLACE_STATEMENT_NOT_READY, $exception->reasonCode);
        }
    }

    public function test_revision_recomputes_exact_total_from_corrected_lines(): void
    {
        [$owner, $organizer, $statement, $dispute, $platformUserId] = $this->disputedStatement('revise-total');

        $originalLine = $statement->lines->first();
        $revision = app(ReviseSettlementStatementAction::class)->execute(
            $platformUserId,
            $statement->public_id,
            $dispute->public_id,
            'billing_error',
            [[
                'asset_public_id' => $originalLine->asset_public_id,
                'unit_price_minor' => 500,
                'billable_units' => 3,
            ]],
            'revise-total-idempotency',
            'revise-total-correlation',
        );

        $lineSum = $revision->lines->sum(fn ($l) => (int) $l->line_total_minor);
        self::assertSame($lineSum, (int) $revision->agreed_total_minor);
    }

    public function test_original_statement_becomes_superseded_and_remains_immutable(): void
    {
        [$owner, $organizer, $statement, $dispute, $platformUserId] = $this->disputedStatement('revise-immutable');

        app(ReviseSettlementStatementAction::class)->execute(
            $platformUserId,
            $statement->public_id,
            $dispute->public_id,
            'factual_correction',
            [],
            'revise-immutable-idempotency',
            'revise-immutable-correlation',
        );

        $original = SettlementStatement::query()->withoutGlobalScopes()
            ->where('id', $statement->id)->first();
        self::assertSame('superseded', $original->status);

        $originalLines = $original->lines;
        self::assertSame(
            $statement->lines->pluck('line_total_minor')->sort()->values()->all(),
            $originalLines->pluck('line_total_minor')->sort()->values()->all(),
        );
    }

    public function test_revision_copies_immutable_rental_and_event_facts(): void
    {
        [$owner, $organizer, $statement, $dispute, $platformUserId] = $this->disputedStatement('revise-facts');

        $revision = app(ReviseSettlementStatementAction::class)->execute(
            $platformUserId,
            $statement->public_id,
            $dispute->public_id,
            'factual_correction',
            [],
            'revise-facts-idempotency',
            'revise-facts-correlation',
        );

        self::assertSame($statement->venue_timezone, $revision->venue_timezone);
        self::assertSame($statement->rental_outcome, $revision->rental_outcome);
    }

    public function test_participants_can_see_both_original_and_revised_statements(): void
    {
        [$owner, $organizer, $statement, $dispute, $platformUserId] = $this->disputedStatement('revise-visible');

        app(ReviseSettlementStatementAction::class)->execute(
            $platformUserId,
            $statement->public_id,
            $dispute->public_id,
            'factual_correction',
            [],
            'revise-visible-idempotency',
            'revise-visible-correlation',
        );

        $ownerCount = SettlementStatement::query()
            ->forParticipant((int) $owner['tenant']->id)
            ->count();
        self::assertSame(2, $ownerCount);

        $organizerCount = SettlementStatement::query()
            ->forParticipant((int) $organizer['tenant']->id)
            ->count();
        self::assertSame(2, $organizerCount);
    }

    /**
     * @return array{0:array,1:array,2:SettlementStatement,3:mixed,4:int}
     */
    private function disputedStatement(string $key): array
    {
        $this->freezeMarketplaceClock();
        $organizer = $this->createTenantMember(tenantAttributes: ['organization_type' => 'organizer']);
        $event = $this->createMarketplaceEvent($organizer['tenant'], $organizer['user']);
        $owner = $this->createTenantMember(tenantAttributes: ['organization_type' => 'venue_owner']);
        $inventory = $this->createPublishedMarketplaceInventory($owner, $key);
        app(TenantContextStore::class)->clear();
        $pubId = $inventory['assets'][3]->publications()->where('status', 'active')->value('public_id');
        $rental = $this->createSubmittedMarketplaceRental($organizer, $event, [$pubId], "{$key}-rental");

        app(CancelRentalAction::class)->execute(
            (int) $organizer['tenant']->id,
            (int) $organizer['user']->id,
            $rental->public_id,
            1,
            "{$key}-cancel-correlation",
        );

        $statement = app(GenerateSettlementStatementAction::class)->execute(
            (int) $owner['tenant']->id,
            $rental->public_id,
            "{$key}-gen-correlation",
        );

        $dispute = app(OpenMarketplaceDisputeAction::class)->execute(
            (int) $owner['tenant']->id,
            (int) $owner['user']->id,
            $statement->public_id,
            'billing_error',
            'The billable units are incorrect for the kiosk asset.',
            "{$key}-dispute-idempotency",
            "{$key}-dispute-correlation",
        );

        $platformUser = \App\Models\User::factory()->create();

        return [$owner, $organizer, $statement, $dispute, (int) $platformUser->id];
    }
}
