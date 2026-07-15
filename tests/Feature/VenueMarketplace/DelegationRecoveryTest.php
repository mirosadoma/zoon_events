<?php

namespace Tests\Feature\VenueMarketplace;

use App\Modules\AccessControl\Application\Contracts\DelegatedAcsAssetPort;
use App\Modules\Kiosk\Application\Contracts\DelegatedKioskAssetPort;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use App\Modules\VenueMarketplace\Application\Actions\ActivateRentalAction;
use App\Modules\VenueMarketplace\Application\Actions\ApproveRentalAction;
use App\Modules\VenueMarketplace\Application\Actions\ProvisionDelegatedAssetsAction;
use App\Modules\VenueMarketplace\Application\Actions\RevokeRentalAction;
use App\Modules\VenueMarketplace\Application\Jobs\ProvisionMarketplaceDelegation;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\ControlDelegation;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\DelegatedAssetResource;
use App\Modules\VenueMarketplace\Testing\Fakes\FakeDelegatedAcsAssetPort;
use App\Modules\VenueMarketplace\Testing\Fakes\FakeDelegatedKioskAssetPort;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesMarketplaceFixture;
use Tests\TestCase;

final class DelegationRecoveryTest extends TestCase
{
    use CreatesMarketplaceFixture;
    use RefreshDatabase;

    public function test_provision_timeout_catches_adapter_exception_as_degraded(): void
    {
        [$owner, , $rental] = $this->approvedRental('timeout');

        $fakeAcs = new FakeDelegatedAcsAssetPort;
        $fakeAcs->fail = true;
        $this->app->instance(DelegatedAcsAssetPort::class, $fakeAcs);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2027-01-10T06:30:00Z'));

        app(ActivateRentalAction::class)->execute(
            (int) $owner['tenant']->id,
            $rental->id,
            'timeout-corr',
        );

        $delegation = ControlDelegation::query()->withoutGlobalScopes()
            ->where('rental_request_id', $rental->id)->first();

        self::assertSame('degraded', $delegation->status);
        self::assertSame('partial_adapter_failure', $delegation->degraded_reason_code);
    }

    public function test_partial_resources_are_tracked_individually(): void
    {
        [$owner, , $rental] = $this->approvedRental('partial');

        $fakeKiosk = new FakeDelegatedKioskAssetPort;
        $fakeKiosk->fail = true;
        $this->app->instance(DelegatedKioskAssetPort::class, $fakeKiosk);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2027-01-10T06:30:00Z'));

        app(ActivateRentalAction::class)->execute(
            (int) $owner['tenant']->id,
            $rental->id,
            'partial-corr',
        );

        $resources = DelegatedAssetResource::query()->withoutGlobalScopes()
            ->where('rental_request_id', $rental->id)->get();

        $statuses = $resources->pluck('provisioning_status')->unique()->sort()->values()->all();
        self::assertContains('degraded', $statuses);
    }

    public function test_retry_convergence_recovers_degraded_to_active(): void
    {
        [$owner, , $rental] = $this->approvedRental('retry');

        $fakeAcs = new FakeDelegatedAcsAssetPort;
        $fakeAcs->fail = true;
        $this->app->instance(DelegatedAcsAssetPort::class, $fakeAcs);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2027-01-10T06:30:00Z'));

        app(ActivateRentalAction::class)->execute(
            (int) $owner['tenant']->id,
            $rental->id,
            'retry-corr-1',
        );

        $delegation = ControlDelegation::query()->withoutGlobalScopes()
            ->where('rental_request_id', $rental->id)->first();
        self::assertSame('degraded', $delegation->status);

        $fakeAcs->fail = false;
        $this->app->instance(DelegatedAcsAssetPort::class, $fakeAcs);

        app(ProvisionDelegatedAssetsAction::class)->execute(
            (int) $owner['tenant']->id,
            $delegation->public_id,
            'retry-corr-2',
        );

        $delegation->refresh();
        self::assertSame('active', $delegation->status);
    }

    public function test_duplicate_delivery_is_idempotent(): void
    {
        [$owner, , $rental] = $this->approvedRental('duplicate');

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2027-01-10T06:30:00Z'));

        app(ActivateRentalAction::class)->execute(
            (int) $owner['tenant']->id,
            $rental->id,
            'dup-corr-1',
        );

        $delegation = ControlDelegation::query()->withoutGlobalScopes()
            ->where('rental_request_id', $rental->id)->first();

        $versionBefore = $delegation->version;

        app(ProvisionDelegatedAssetsAction::class)->execute(
            (int) $owner['tenant']->id,
            $delegation->public_id,
            'dup-corr-2',
        );

        $delegation->refresh();
        self::assertSame($versionBefore + 1, $delegation->version);
    }

    public function test_owner_revoke_during_recovery_stops_provisioning(): void
    {
        [$owner, , $rental] = $this->approvedRental('revoke-recovery');

        $fakeAcs = new FakeDelegatedAcsAssetPort;
        $fakeAcs->fail = true;
        $this->app->instance(DelegatedAcsAssetPort::class, $fakeAcs);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2027-01-10T06:30:00Z'));

        app(ActivateRentalAction::class)->execute(
            (int) $owner['tenant']->id,
            $rental->id,
            'revoke-recovery-corr-1',
        );

        app(RevokeRentalAction::class)->execute(
            (int) $owner['tenant']->id,
            (int) $owner['user']->id,
            $rental->public_id,
            'Emergency stop',
            (int) $rental->fresh()->version,
            'revoke-recovery-corr-2',
        );

        $delegation = ControlDelegation::query()->withoutGlobalScopes()
            ->where('rental_request_id', $rental->id)->first();

        self::assertSame('revoked', $delegation->status);
    }

    public function test_provision_job_retry_configuration(): void
    {
        $job = new ProvisionMarketplaceDelegation(1, 'pub-id', 'corr');

        self::assertSame(5, $job->tries);
        self::assertSame(3, $job->maxExceptions);
        self::assertSame([30, 60, 120, 300, 600], $job->backoff);
        self::assertTrue($job->afterCommit);
    }

    public function test_operator_visible_degraded_reason(): void
    {
        [$owner, , $rental] = $this->approvedRental('degraded-reason');

        $fakeAcs = new FakeDelegatedAcsAssetPort;
        $fakeAcs->fail = true;
        $this->app->instance(DelegatedAcsAssetPort::class, $fakeAcs);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2027-01-10T06:30:00Z'));

        app(ActivateRentalAction::class)->execute(
            (int) $owner['tenant']->id,
            $rental->id,
            'degraded-reason-corr',
        );

        $delegation = ControlDelegation::query()->withoutGlobalScopes()
            ->where('rental_request_id', $rental->id)->first();

        self::assertNotNull($delegation->degraded_reason_code);
        self::assertSame('partial_adapter_failure', $delegation->degraded_reason_code);

        $failedResource = DelegatedAssetResource::query()->withoutGlobalScopes()
            ->where('control_delegation_id', $delegation->id)
            ->where('provisioning_status', 'degraded')
            ->first();

        if ($failedResource !== null) {
            self::assertNotNull($failedResource->failure_reason_code);
        }
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
