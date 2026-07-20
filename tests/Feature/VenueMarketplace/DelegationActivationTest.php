<?php

namespace Tests\Feature\VenueMarketplace;

use App\Modules\AccessControl\Application\Contracts\DelegatedAcsAssetPort;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use App\Modules\VenueMarketplace\Application\Actions\ActivateRentalAction;
use App\Modules\VenueMarketplace\Application\Actions\ApproveRentalAction;
use App\Modules\VenueMarketplace\Application\Jobs\ProvisionMarketplaceDelegation;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\ControlDelegation;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\DelegatedAssetResource;
use App\Modules\VenueMarketplace\Testing\Fakes\FakeDelegatedAcsAssetPort;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\CreatesMarketplaceFixture;
use Tests\TestCase;

final class DelegationActivationTest extends TestCase
{
    use CreatesMarketplaceFixture;
    use RefreshDatabase;

    public function test_pending_delegation_is_not_activated_before_start(): void
    {
        [$owner, $organizer, $rental] = $this->approvedRental('pending-before');

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-14T12:00:00Z'));

        $result = app(ActivateRentalAction::class)->execute(
            (int) $owner['tenant']->id,
            $rental->id,
            'pending-before-correlation',
        );

        self::assertSame('approved', $result->status);
        self::assertSame('pending', ControlDelegation::query()->withoutGlobalScopes()
            ->where('rental_request_id', $rental->id)->value('status'));
    }

    public function test_active_materialization_provisions_delegation_resources(): void
    {
        [$owner, $organizer, $rental] = $this->approvedRental('activate');

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2027-01-10T06:30:00Z'));

        $result = app(ActivateRentalAction::class)->execute(
            (int) $owner['tenant']->id,
            $rental->id,
            'activate-correlation',
        );

        self::assertSame('active', $result->status);

        $delegation = ControlDelegation::query()->withoutGlobalScopes()
            ->where('rental_request_id', $rental->id)->first();

        self::assertContains($delegation->status, ['active', 'degraded']);
        self::assertGreaterThanOrEqual(1, (int) $delegation->provision_attempts);
    }

    public function test_selected_capability_provisioning_records_accepted_capabilities(): void
    {
        [$owner, $organizer, $rental] = $this->approvedRental('capability');

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2027-01-10T06:30:00Z'));

        app(ActivateRentalAction::class)->execute(
            (int) $owner['tenant']->id,
            $rental->id,
            'capability-correlation',
        );

        $resources = DelegatedAssetResource::query()->withoutGlobalScopes()
            ->where('rental_request_id', $rental->id)->get();

        foreach ($resources as $resource) {
            if ($resource->provisioning_status === 'not_applicable') {
                continue;
            }
            self::assertNotEmpty($resource->granted_capabilities);
        }
    }

    public function test_partial_adapter_failure_results_in_degraded_delegation(): void
    {
        [$owner, $organizer, $rental] = $this->approvedRental('degraded');

        $fakeAcs = new FakeDelegatedAcsAssetPort;
        $fakeAcs->fail = true;
        $this->app->instance(DelegatedAcsAssetPort::class, $fakeAcs);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2027-01-10T06:30:00Z'));

        app(ActivateRentalAction::class)->execute(
            (int) $owner['tenant']->id,
            $rental->id,
            'degraded-correlation',
        );

        $delegation = ControlDelegation::query()->withoutGlobalScopes()
            ->where('rental_request_id', $rental->id)->first();

        self::assertSame('degraded', $delegation->status);
        self::assertSame('partial_adapter_failure', $delegation->degraded_reason_code);
    }

    public function test_idempotent_activation_does_not_duplicate_rows(): void
    {
        [$owner, $organizer, $rental] = $this->approvedRental('idempotent');

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2027-01-10T06:30:00Z'));

        $first = app(ActivateRentalAction::class)->execute(
            (int) $owner['tenant']->id,
            $rental->id,
            'idempotent-1',
        );
        $second = app(ActivateRentalAction::class)->execute(
            (int) $owner['tenant']->id,
            $rental->id,
            'idempotent-2',
        );

        self::assertSame('active', $first->status);
        self::assertSame($first->id, $second->id);
    }

    public function test_provision_job_dispatches_after_commit(): void
    {
        Queue::fake([ProvisionMarketplaceDelegation::class]);

        $job = new ProvisionMarketplaceDelegation(1, 'test-pub-id', 'corr');

        self::assertTrue($job->afterCommit);
        self::assertSame(5, $job->tries);
        self::assertSame('provision:1:test-pub-id', $job->uniqueId());
    }

    public function test_canonical_end_unchanged_after_activation(): void
    {
        [$owner, $organizer, $rental] = $this->approvedRental('canonical-end');

        $delegationBefore = ControlDelegation::query()->withoutGlobalScopes()
            ->where('rental_request_id', $rental->id)->first();
        $originalEndsAt = $delegationBefore->ends_at;

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2027-01-10T06:30:00Z'));

        app(ActivateRentalAction::class)->execute(
            (int) $owner['tenant']->id,
            $rental->id,
            'canonical-end-correlation',
        );

        $delegationAfter = ControlDelegation::query()->withoutGlobalScopes()
            ->where('rental_request_id', $rental->id)->first();

        self::assertEquals($originalEndsAt, $delegationAfter->ends_at);
    }

    /**
     * @return array{0:array,1:array,2:mixed}
     */
    private function approvedRental(string $key): array
    {
        $this->freezeMarketplaceClock();
        $organizer = $this->createTenantMember(tenantAttributes: ['organization_type' => 'organizer']);
        $event = $this->createMarketplaceEvent($organizer['tenant'], $organizer['user']);
        $owner = $this->createTenantMember(tenantAttributes: ['organization_type' => 'venue_owner']);
        $inventory = $this->createPublishedMarketplaceInventory($owner, $key);
        app(TenantContextStore::class)->clear();

        $publicationPublicId = $inventory['assets'][3]->publications()
            ->where('status', 'active')
            ->value('public_id');

        $rental = $this->createSubmittedMarketplaceRental(
            $organizer,
            $event,
            [$publicationPublicId],
            "{$key}-rental",
        );

        $rental = app(ApproveRentalAction::class)->execute(
            (int) $owner['tenant']->id,
            (int) $owner['user']->id,
            $rental->public_id,
            1,
            "{$key}-approve-idem",
            "{$key}-approve-corr",
        );

        return [$owner, $organizer, $rental];
    }
}
