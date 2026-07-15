<?php

namespace Tests\Feature\VenueMarketplace;

use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use App\Modules\VenueMarketplace\Application\Actions\CancelRentalAction;
use App\Modules\VenueMarketplace\Application\Actions\GenerateSettlementStatementAction;
use App\Modules\VenueMarketplace\Application\Actions\OpenMarketplaceDisputeAction;
use App\Modules\VenueMarketplace\Application\Queries\GetParticipantStatementQuery;
use App\Modules\VenueMarketplace\Application\Queries\ListParticipantStatementsQuery;
use App\Modules\VenueMarketplace\Domain\Exceptions\MarketplaceDomainException;
use App\Modules\VenueMarketplace\Http\Problems\Phase6Problem;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\MarketplaceDispute;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\SettlementStatement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesMarketplaceFixture;
use Tests\TestCase;

final class MarketplaceStatementDisputeIsolationTest extends TestCase
{
    use CreatesMarketplaceFixture;
    use RefreshDatabase;

    public function test_owner_a_cannot_see_owner_b_statements(): void
    {
        [$ownerA, , $statementA] = $this->issuedStatement('iso-a');
        [$ownerB, , $statementB] = $this->issuedStatement('iso-b');

        $ownerAStatements = app(ListParticipantStatementsQuery::class)
            ->execute((int) $ownerA['tenant']->id);
        self::assertCount(1, $ownerAStatements->items);
        self::assertSame($statementA->public_id, $ownerAStatements->items[0]->public_id);

        try {
            app(GetParticipantStatementQuery::class)
                ->execute((int) $ownerA['tenant']->id, $statementB->public_id);
            self::fail('Expected cross-owner access to be denied.');
        } catch (MarketplaceDomainException $exception) {
            self::assertSame(Phase6Problem::MARKETPLACE_STATEMENT_NOT_FOUND, $exception->reasonCode);
        }
    }

    public function test_organizer_cannot_see_unrelated_owner_statements(): void
    {
        [$ownerA, $organizerA, $statementA] = $this->issuedStatement('iso-org-a');
        [$ownerB, $organizerB, $statementB] = $this->issuedStatement('iso-org-b');

        try {
            app(GetParticipantStatementQuery::class)
                ->execute((int) $organizerA['tenant']->id, $statementB->public_id);
            self::fail('Expected cross-organizer access to be denied.');
        } catch (MarketplaceDomainException $exception) {
            self::assertSame(Phase6Problem::MARKETPLACE_STATEMENT_NOT_FOUND, $exception->reasonCode);
        }
    }

    public function test_dispute_cannot_be_opened_by_unrelated_participant(): void
    {
        [$ownerA, , $statementA] = $this->issuedStatement('iso-disp-a');
        $unrelated = $this->createTenantMember(tenantAttributes: ['organization_type' => 'organizer']);

        try {
            app(OpenMarketplaceDisputeAction::class)->execute(
                (int) $unrelated['tenant']->id,
                (int) $unrelated['user']->id,
                $statementA->public_id,
                'billing_error',
                'Attempting unauthorized dispute.',
                'iso-disp-unrelated-idem',
                'iso-disp-unrelated-corr',
            );
            self::fail('Expected unrelated participant to be denied.');
        } catch (MarketplaceDomainException $exception) {
            self::assertSame(Phase6Problem::MARKETPLACE_STATEMENT_NOT_FOUND, $exception->reasonCode);
        }
    }

    public function test_dispute_for_owner_a_does_not_appear_for_owner_b(): void
    {
        [$ownerA, $organizerA, $statementA] = $this->issuedStatement('iso-disp-vis-a');
        [$ownerB, $organizerB, $statementB] = $this->issuedStatement('iso-disp-vis-b');

        $disputeA = app(OpenMarketplaceDisputeAction::class)->execute(
            (int) $ownerA['tenant']->id,
            (int) $ownerA['user']->id,
            $statementA->public_id,
            'billing_error',
            'Test dispute isolation A.',
            'iso-disp-vis-a-idem',
            'iso-disp-vis-a-corr',
        );

        $ownerBDisputes = MarketplaceDispute::query()
            ->forParticipant((int) $ownerB['tenant']->id)
            ->get();
        self::assertCount(0, $ownerBDisputes);

        $ownerADisputes = MarketplaceDispute::query()
            ->forParticipant((int) $ownerA['tenant']->id)
            ->get();
        self::assertCount(1, $ownerADisputes);
    }

    public function test_shared_event_with_multiple_owners_maintains_isolation(): void
    {
        $this->freezeMarketplaceClock();
        $organizer = $this->createTenantMember(tenantAttributes: ['organization_type' => 'organizer']);
        $event = $this->createMarketplaceEvent($organizer['tenant'], $organizer['user']);

        $ownerA = $this->createTenantMember(tenantAttributes: ['organization_type' => 'venue_owner']);
        $inventoryA = $this->createPublishedMarketplaceInventory($ownerA, 'iso-shared-a');
        app(TenantContextStore::class)->clear();
        $pubIdA = $inventoryA['assets'][3]->publications()->where('status', 'active')->value('public_id');
        $rentalA = $this->createSubmittedMarketplaceRental($organizer, $event, [$pubIdA], 'iso-shared-a-rental');

        $ownerB = $this->createTenantMember(tenantAttributes: ['organization_type' => 'venue_owner']);
        $inventoryB = $this->createPublishedMarketplaceInventory($ownerB, 'iso-shared-b');
        app(TenantContextStore::class)->clear();
        $pubIdB = $inventoryB['assets'][3]->publications()->where('status', 'active')->value('public_id');
        $rentalB = $this->createSubmittedMarketplaceRental($organizer, $event, [$pubIdB], 'iso-shared-b-rental');

        app(CancelRentalAction::class)->execute(
            (int) $organizer['tenant']->id, (int) $organizer['user']->id,
            $rentalA->public_id, 1, 'iso-shared-cancel-a',
        );
        app(CancelRentalAction::class)->execute(
            (int) $organizer['tenant']->id, (int) $organizer['user']->id,
            $rentalB->public_id, 1, 'iso-shared-cancel-b',
        );

        $statementA = app(GenerateSettlementStatementAction::class)->execute(
            (int) $ownerA['tenant']->id, $rentalA->public_id, 'iso-shared-gen-a',
        );
        $statementB = app(GenerateSettlementStatementAction::class)->execute(
            (int) $ownerB['tenant']->id, $rentalB->public_id, 'iso-shared-gen-b',
        );

        $ownerAList = app(ListParticipantStatementsQuery::class)
            ->execute((int) $ownerA['tenant']->id);
        self::assertCount(1, $ownerAList->items);
        self::assertSame($statementA->public_id, $ownerAList->items[0]->public_id);

        $ownerBList = app(ListParticipantStatementsQuery::class)
            ->execute((int) $ownerB['tenant']->id);
        self::assertCount(1, $ownerBList->items);
        self::assertSame($statementB->public_id, $ownerBList->items[0]->public_id);

        $organizerList = app(ListParticipantStatementsQuery::class)
            ->execute((int) $organizer['tenant']->id);
        self::assertCount(2, $organizerList->items);
    }

    /**
     * @return array{0:array,1:array,2:SettlementStatement}
     */
    private function issuedStatement(string $key): array
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
            "{$key}-cancel",
        );

        $statement = app(GenerateSettlementStatementAction::class)->execute(
            (int) $owner['tenant']->id,
            $rental->public_id,
            "{$key}-gen",
        );

        return [$owner, $organizer, $statement];
    }
}
