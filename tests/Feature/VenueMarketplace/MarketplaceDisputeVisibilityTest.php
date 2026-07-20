<?php

namespace Tests\Feature\VenueMarketplace;

use App\Models\User;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use App\Modules\VenueMarketplace\Application\Actions\AddMarketplaceDisputeNoteAction;
use App\Modules\VenueMarketplace\Application\Actions\CancelRentalAction;
use App\Modules\VenueMarketplace\Application\Actions\GenerateSettlementStatementAction;
use App\Modules\VenueMarketplace\Application\Actions\OpenMarketplaceDisputeAction;
use App\Modules\VenueMarketplace\Application\Actions\ResolveMarketplaceDisputeAction;
use App\Modules\VenueMarketplace\Application\Actions\StartMarketplaceDisputeReviewAction;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\MarketplaceDispute;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\MarketplaceDisputeEvent;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\SettlementStatement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesMarketplaceFixture;
use Tests\TestCase;

final class MarketplaceDisputeVisibilityTest extends TestCase
{
    use CreatesMarketplaceFixture;
    use RefreshDatabase;

    public function test_participant_can_open_dispute_with_opening_event_visible(): void
    {
        [$owner, $organizer, $statement] = $this->issuedStatement('vis-open');

        $dispute = app(OpenMarketplaceDisputeAction::class)->execute(
            (int) $owner['tenant']->id,
            (int) $owner['user']->id,
            $statement->public_id,
            'billing_error',
            'The unit count is wrong.',
            'vis-open-idem',
            'vis-open-corr',
        );

        self::assertSame('open', $dispute->status);
        self::assertCount(1, $dispute->events);

        $openEvent = $dispute->events->first();
        self::assertSame('opened', $openEvent->event_type);
        self::assertSame('owner', $openEvent->actor_scope);
        self::assertSame('participants', $openEvent->visibility);
    }

    public function test_organizer_can_also_open_dispute(): void
    {
        [$owner, $organizer, $statement] = $this->issuedStatement('vis-org-open');

        $dispute = app(OpenMarketplaceDisputeAction::class)->execute(
            (int) $organizer['tenant']->id,
            (int) $organizer['user']->id,
            $statement->public_id,
            'incorrect_duration',
            'The rental window is wrong.',
            'vis-org-open-idem',
            'vis-org-open-corr',
        );

        self::assertSame('open', $dispute->status);
        $openEvent = $dispute->events->first();
        self::assertSame('organizer', $openEvent->actor_scope);
    }

    public function test_platform_internal_notes_are_not_participant_visible(): void
    {
        [$owner, $organizer, $statement, $dispute] = $this->openedDispute('vis-internal');
        $platformUser = User::factory()->create();

        app(AddMarketplaceDisputeNoteAction::class)->execute(
            (int) $platformUser->id,
            $dispute->public_id,
            'Internal investigation note.',
            'platform_only',
            'vis-internal-idem',
            'vis-internal-corr',
        );

        $disputeWithEvents = MarketplaceDispute::query()
            ->withoutGlobalScopes()
            ->where('id', $dispute->id)
            ->with('events')
            ->first();

        $allEvents = $disputeWithEvents->events;
        self::assertGreaterThanOrEqual(2, $allEvents->count());

        $internalEvents = $allEvents->filter(fn ($e) => $e->visibility === 'platform_only');
        self::assertCount(1, $internalEvents);

        $participantEvents = $disputeWithEvents->participantEvents()->get();
        self::assertTrue($participantEvents->every(fn ($e) => $e->visibility === 'participants'));
    }

    public function test_participant_visible_notes_appear_for_both_participants(): void
    {
        [$owner, $organizer, $statement, $dispute] = $this->openedDispute('vis-participant');
        $platformUser = User::factory()->create();

        app(AddMarketplaceDisputeNoteAction::class)->execute(
            (int) $platformUser->id,
            $dispute->public_id,
            'We are looking into this.',
            'participants',
            'vis-participant-idem',
            'vis-participant-corr',
        );

        $participantEvents = MarketplaceDisputeEvent::query()
            ->withoutGlobalScopes()
            ->where('marketplace_dispute_id', $dispute->id)
            ->where('visibility', 'participants')
            ->get();

        self::assertGreaterThanOrEqual(2, $participantEvents->count());
        self::assertTrue($participantEvents->contains(fn ($e) => $e->event_type === 'note_added'));
    }

    public function test_resolution_event_is_participant_visible(): void
    {
        [$owner, $organizer, $statement, $dispute] = $this->openedDispute('vis-resolve');
        $platformUser = User::factory()->create();

        $resolved = app(ResolveMarketplaceDisputeAction::class)->execute(
            (int) $platformUser->id,
            $dispute->public_id,
            'resolve',
            'confirmed_error',
            'The billing error has been confirmed and corrected.',
            'vis-resolve-idem',
            'vis-resolve-corr',
        );

        self::assertSame('resolved', $resolved->status);

        $resolutionEvent = $resolved->events->firstWhere('event_type', 'resolved');
        self::assertNotNull($resolutionEvent);
        self::assertSame('participants', $resolutionEvent->visibility);
        self::assertSame('platform', $resolutionEvent->actor_scope);
    }

    public function test_dispute_events_use_opaque_public_ids_not_internal_ids(): void
    {
        [$owner, $organizer, $statement, $dispute] = $this->openedDispute('vis-opaque');

        $hidden = $dispute->getHidden();
        self::assertContains('id', $hidden);
        self::assertContains('tenant_id', $hidden);
        self::assertContains('organizer_tenant_id', $hidden);
        self::assertContains('rental_request_id', $hidden);
        self::assertContains('settlement_statement_id', $hidden);
        self::assertContains('reported_by_tenant_id', $hidden);
        self::assertContains('reported_by_user_id', $hidden);

        $event = $dispute->events->first();
        $eventHidden = $event->getHidden();
        self::assertContains('id', $eventHidden);
        self::assertContains('tenant_id', $eventHidden);
        self::assertContains('marketplace_dispute_id', $eventHidden);
        self::assertContains('actor_user_id', $eventHidden);
    }

    public function test_original_statement_is_immutable_after_dispute_opens(): void
    {
        [$owner, $organizer, $statement, $dispute] = $this->openedDispute('vis-immutable');

        $fresh = SettlementStatement::query()->withoutGlobalScopes()
            ->where('id', $statement->id)->first();

        self::assertSame('issued', $fresh->status);
        self::assertSame((int) $statement->agreed_total_minor, (int) $fresh->agreed_total_minor);
        self::assertSame((int) $statement->revision, (int) $fresh->revision);
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

    /**
     * @return array{0:array,1:array,2:SettlementStatement,3:MarketplaceDispute}
     */
    private function openedDispute(string $key): array
    {
        [$owner, $organizer, $statement] = $this->issuedStatement($key);

        $dispute = app(OpenMarketplaceDisputeAction::class)->execute(
            (int) $owner['tenant']->id,
            (int) $owner['user']->id,
            $statement->public_id,
            'billing_error',
            'Test dispute for visibility check.',
            "{$key}-dispute-idem",
            "{$key}-dispute-corr",
        );

        return [$owner, $organizer, $statement, $dispute];
    }
}
