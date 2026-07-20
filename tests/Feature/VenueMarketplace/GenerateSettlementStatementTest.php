<?php

namespace Tests\Feature\VenueMarketplace;

use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use App\Modules\VenueMarketplace\Application\Actions\ApproveRentalAction;
use App\Modules\VenueMarketplace\Application\Actions\CancelRentalAction;
use App\Modules\VenueMarketplace\Application\Actions\GenerateSettlementStatementAction;
use App\Modules\VenueMarketplace\Application\Actions\RevokeRentalAction;
use App\Modules\VenueMarketplace\Domain\Exceptions\MarketplaceDomainException;
use App\Modules\VenueMarketplace\Http\Problems\Phase6Problem;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\SettlementStatement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesMarketplaceFixture;
use Tests\TestCase;

final class GenerateSettlementStatementTest extends TestCase
{
    use CreatesMarketplaceFixture;
    use RefreshDatabase;

    public function test_generates_statement_for_completed_rental_with_revision_1(): void
    {
        [$owner, $rental] = $this->terminalRental('completed', 'gen-completed');

        $statement = app(GenerateSettlementStatementAction::class)->execute(
            (int) $owner['tenant']->id,
            $rental->public_id,
            'gen-completed-correlation',
        );

        self::assertSame('issued', $statement->status);
        self::assertSame(1, (int) $statement->revision);
        self::assertSame('completed', $statement->rental_outcome);
        self::assertSame($rental->currency, $statement->currency);
        self::assertSame((int) $rental->total_minor, (int) $statement->agreed_total_minor);
        self::assertNotNull($statement->issued_at);
        self::assertNotNull($statement->public_id);
        self::assertNotNull($statement->statement_number);
        self::assertCount($rental->assets->count(), $statement->lines);
    }

    public function test_generates_statement_for_revoked_rental(): void
    {
        [$owner, $rental] = $this->terminalRental('revoked', 'gen-revoked');

        $statement = app(GenerateSettlementStatementAction::class)->execute(
            (int) $owner['tenant']->id,
            $rental->public_id,
            'gen-revoked-correlation',
        );

        self::assertSame('issued', $statement->status);
        self::assertSame('revoked', $statement->rental_outcome);
    }

    public function test_generates_statement_for_cancelled_rental(): void
    {
        [$owner, $rental] = $this->terminalRental('cancelled', 'gen-cancelled');

        $statement = app(GenerateSettlementStatementAction::class)->execute(
            (int) $owner['tenant']->id,
            $rental->public_id,
            'gen-cancelled-correlation',
        );

        self::assertSame('issued', $statement->status);
        self::assertSame('cancelled', $statement->rental_outcome);
    }

    public function test_idempotent_returns_same_statement_on_duplicate_call(): void
    {
        [$owner, $rental] = $this->terminalRental('completed', 'gen-idem');
        $action = app(GenerateSettlementStatementAction::class);

        $first = $action->execute(
            (int) $owner['tenant']->id, $rental->public_id, 'idem-corr-1',
        );
        $second = $action->execute(
            (int) $owner['tenant']->id, $rental->public_id, 'idem-corr-2',
        );

        self::assertSame($first->id, $second->id);
        self::assertSame(1, SettlementStatement::query()->withoutGlobalScopes()
            ->where('rental_request_id', $rental->id)->count());
    }

    public function test_non_terminal_rental_rejects_generation(): void
    {
        $this->freezeMarketplaceClock();
        $organizer = $this->createTenantMember(tenantAttributes: ['organization_type' => 'organizer']);
        $event = $this->createMarketplaceEvent($organizer['tenant'], $organizer['user']);
        $owner = $this->createTenantMember(tenantAttributes: ['organization_type' => 'venue_owner']);
        $inventory = $this->createPublishedMarketplaceInventory($owner, 'gen-active');
        app(TenantContextStore::class)->clear();
        $pubId = $inventory['assets'][3]->publications()->where('status', 'active')->value('public_id');
        $rental = $this->createSubmittedMarketplaceRental($organizer, $event, [$pubId], 'gen-active');

        try {
            app(GenerateSettlementStatementAction::class)->execute(
                (int) $owner['tenant']->id,
                $rental->public_id,
                'gen-active-correlation',
            );
            self::fail('Expected non-terminal rental to be denied.');
        } catch (MarketplaceDomainException $exception) {
            self::assertSame(Phase6Problem::MARKETPLACE_STATEMENT_NOT_READY, $exception->reasonCode);
        }
    }

    public function test_statement_lines_snapshot_agreed_facts(): void
    {
        [$owner, $rental] = $this->terminalRental('completed', 'gen-snapshot');

        $statement = app(GenerateSettlementStatementAction::class)->execute(
            (int) $owner['tenant']->id, $rental->public_id, 'snapshot-corr',
        );

        $rentalAssets = $rental->fresh(['assets'])->assets;
        foreach ($statement->lines as $line) {
            $rentalAsset = $rentalAssets->firstWhere('asset_public_id', $line->asset_public_id);
            self::assertNotNull($rentalAsset);
            self::assertSame((int) $rentalAsset->unit_price_minor, (int) $line->unit_price_minor);
            self::assertSame((int) $rentalAsset->billable_units, (int) $line->billable_units);
            self::assertSame(
                (int) $line->unit_price_minor * (int) $line->billable_units,
                (int) $line->line_total_minor,
            );
        }

        $lineSum = $statement->lines->sum(fn ($l) => (int) $l->line_total_minor);
        self::assertSame($lineSum, (int) $statement->agreed_total_minor);
    }

    public function test_all_lines_share_statement_currency(): void
    {
        [$owner, $rental] = $this->terminalRental('completed', 'gen-currency');

        $statement = app(GenerateSettlementStatementAction::class)->execute(
            (int) $owner['tenant']->id, $rental->public_id, 'currency-corr',
        );

        foreach ($statement->lines as $line) {
            self::assertSame($statement->currency, $line->currency);
        }
    }

    /**
     * @return array{0:array,1:mixed}
     */
    private function terminalRental(string $outcome, string $key): array
    {
        $this->freezeMarketplaceClock();
        $organizer = $this->createTenantMember(tenantAttributes: ['organization_type' => 'organizer']);
        $event = $this->createMarketplaceEvent($organizer['tenant'], $organizer['user']);
        $owner = $this->createTenantMember(tenantAttributes: ['organization_type' => 'venue_owner']);
        $inventory = $this->createPublishedMarketplaceInventory($owner, $key);
        app(TenantContextStore::class)->clear();
        $pubId = $inventory['assets'][3]->publications()->where('status', 'active')->value('public_id');
        $rental = $this->createSubmittedMarketplaceRental($organizer, $event, [$pubId], "{$key}-rental");

        match ($outcome) {
            'completed' => $this->approveAndComplete($owner, $rental),
            'revoked' => $this->approveAndRevoke($owner, $rental),
            'cancelled' => app(CancelRentalAction::class)->execute(
                (int) $organizer['tenant']->id,
                (int) $organizer['user']->id,
                $rental->public_id,
                1,
                "{$key}-cancel-correlation",
            ),
        };

        return [$owner, $rental->fresh(['assets'])];
    }

    private function approveAndComplete(array $owner, mixed $rental): void
    {
        $rental = app(ApproveRentalAction::class)->execute(
            (int) $owner['tenant']->id,
            (int) $owner['user']->id,
            $rental->public_id,
            1,
            'gen-approve-idempotency',
            'gen-approve-correlation',
        );

        $rental->forceFill(['status' => 'completed', 'completed_at' => now()])->save();
    }

    private function approveAndRevoke(array $owner, mixed $rental): void
    {
        app(ApproveRentalAction::class)->execute(
            (int) $owner['tenant']->id,
            (int) $owner['user']->id,
            $rental->public_id,
            1,
            'gen-revoke-approve-idempotency',
            'gen-revoke-approve-correlation',
        );

        app(RevokeRentalAction::class)->execute(
            (int) $owner['tenant']->id,
            (int) $owner['user']->id,
            $rental->public_id,
            'Safety shutdown',
            2,
            'gen-revoke-correlation',
        );
    }
}
