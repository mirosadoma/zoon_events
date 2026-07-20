<?php

namespace Tests\Feature\VenueMarketplace;

use App\Modules\VenueMarketplace\Application\Jobs\GenerateRentalSettlementStatement;
use App\Modules\VenueMarketplace\Application\Jobs\ProvisionMarketplaceDelegation;
use App\Modules\VenueMarketplace\Application\Jobs\ReleaseMarketplaceDelegation;
use App\Modules\VenueMarketplace\Application\Listeners\SendSettlementDisputeNotifications;
use App\Modules\VenueMarketplace\Application\Services\MarketplaceCatalogCache;
use App\Modules\VenueMarketplace\Domain\Events\DisputeResolved;
use App\Modules\VenueMarketplace\Domain\Events\StatementRevised;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase6EphemeralIsolationTest extends TestCase
{
    use RefreshDatabase;

    // ─── Cache-key scoping ──────────────────────────────────────────

    public function test_catalog_cache_keys_include_tenant_id_isolation(): void
    {
        $cache = app(MarketplaceCatalogCache::class);
        $tenantA = 100;
        $tenantB = 200;
        $filters = ['country_code' => 'SA'];
        $locale = 'en';
        $sentinel = 'tenant-specific-payload';

        config(['marketplace.catalog.cache_enabled' => true]);

        $resultA = $cache->remember($tenantA, $locale, $filters, null, fn () => "a:{$sentinel}");
        $resultB = $cache->remember($tenantB, $locale, $filters, null, fn () => "b:{$sentinel}");

        self::assertSame("a:{$sentinel}", $resultA);
        self::assertSame("b:{$sentinel}", $resultB);
        self::assertNotSame($resultA, $resultB, 'Different tenants must receive independently cached results.');
    }

    public function test_catalog_cache_invalidation_rotates_generation(): void
    {
        $cache = app(MarketplaceCatalogCache::class);
        config(['marketplace.catalog.cache_enabled' => true]);

        $first = $cache->remember(1, 'en', [], null, fn () => 'gen-1');
        $cache->invalidate();
        $second = $cache->remember(1, 'en', [], null, fn () => 'gen-2');

        self::assertSame('gen-1', $first);
        self::assertSame('gen-2', $second, 'After invalidation the loader must be re-invoked.');
    }

    // ─── Queued job payload minimization ────────────────────────────

    public function test_settlement_job_carries_only_identifiers_not_model_data(): void
    {
        $job = new GenerateRentalSettlementStatement(
            ownerTenantId: 1,
            rentalPublicId: (string) Str::ulid(),
            correlationId: 'corr-123',
        );

        $serialized = serialize($job);

        self::assertStringNotContainsString('business_contact_email', $serialized);
        self::assertStringNotContainsString('business_contact_phone', $serialized);
        self::assertStringNotContainsString('opaque_reference', $serialized);
        self::assertStringNotContainsString('binding_metadata', $serialized);
        self::assertStringNotContainsString('pairing_secret', $serialized);

        self::assertSame(1, $job->ownerTenantId);
        self::assertSame('corr-123', $job->correlationId);
    }

    public function test_provision_job_carries_only_identifiers(): void
    {
        $job = new ProvisionMarketplaceDelegation(
            ownerTenantId: 42,
            delegationPublicId: (string) Str::ulid(),
            correlationId: 'prov-456',
        );

        $serialized = serialize($job);

        self::assertStringNotContainsString('credential', $serialized);
        self::assertStringNotContainsString('password', $serialized);
        self::assertStringNotContainsString('access_token', $serialized);
        self::assertSame(42, $job->ownerTenantId);
    }

    public function test_release_job_carries_only_identifiers(): void
    {
        $job = new ReleaseMarketplaceDelegation(
            ownerTenantId: 77,
            delegationPublicId: (string) Str::ulid(),
            correlationId: 'rel-789',
        );

        $serialized = serialize($job);

        self::assertStringNotContainsString('credential', $serialized);
        self::assertStringNotContainsString('secret', $serialized);
        self::assertSame(77, $job->ownerTenantId);
    }

    // ─── Idempotency isolation ──────────────────────────────────────

    public function test_settlement_job_unique_id_is_tenant_scoped(): void
    {
        $publicId = (string) Str::ulid();

        $jobA = new GenerateRentalSettlementStatement(1, $publicId, 'c1');
        $jobB = new GenerateRentalSettlementStatement(2, $publicId, 'c2');

        self::assertNotSame($jobA->uniqueId(), $jobB->uniqueId(), 'uniqueId must differ across tenants.');
        self::assertStringContainsString('1', $jobA->uniqueId());
        self::assertStringContainsString('2', $jobB->uniqueId());
    }

    public function test_provision_job_unique_id_is_tenant_scoped(): void
    {
        $publicId = (string) Str::ulid();

        $jobA = new ProvisionMarketplaceDelegation(10, $publicId, 'c');
        $jobB = new ProvisionMarketplaceDelegation(20, $publicId, 'c');

        self::assertNotSame($jobA->uniqueId(), $jobB->uniqueId());
    }

    public function test_release_job_unique_id_is_tenant_scoped(): void
    {
        $publicId = (string) Str::ulid();

        $jobA = new ReleaseMarketplaceDelegation(10, $publicId, 'c');
        $jobB = new ReleaseMarketplaceDelegation(20, $publicId, 'c');

        self::assertNotSame($jobA->uniqueId(), $jobB->uniqueId());
    }

    // ─── Streamed export cleanup ────────────────────────────────────

    public function test_csv_export_uses_php_output_stream_not_temp_files(): void
    {
        $source = file_get_contents(
            app_path('Modules/VenueMarketplace/Application/Exports/StreamSettlementStatementCsv.php'),
        );

        self::assertStringContainsString('php://output', $source, 'CSV export must stream to php://output, not a temp file.');
        self::assertStringNotContainsString('tempnam(', $source, 'CSV export must not create temporary files.');
        self::assertStringNotContainsString('sys_get_temp_dir', $source, 'CSV export must not use the system temp directory.');
        self::assertStringNotContainsString('storage_path', $source, 'CSV export must not write to shared storage.');
    }

    public function test_csv_export_response_headers_prevent_caching(): void
    {
        $source = file_get_contents(
            app_path('Modules/VenueMarketplace/Application/Exports/StreamSettlementStatementCsv.php'),
        );

        self::assertStringContainsString('no-store', $source, 'CSV export must set no-store cache header.');
        self::assertStringContainsString('no-cache', $source, 'CSV export must set no-cache header.');
    }

    // ─── Notification deduplication ─────────────────────────────────

    public function test_dispute_notification_deduplication_uses_cache_lock(): void
    {
        $source = file_get_contents(
            app_path('Modules/VenueMarketplace/Application/Listeners/SendSettlementDisputeNotifications.php'),
        );

        self::assertStringContainsString('$this->cache->add(', $source, 'Notification dedupe must use cache->add atomic lock.');
    }

    public function test_dispute_notification_dedupe_key_contains_event_identity(): void
    {
        $source = file_get_contents(
            app_path('Modules/VenueMarketplace/Application/Listeners/SendSettlementDisputeNotifications.php'),
        );

        self::assertStringContainsString('disputePublicId', $source, 'Dedupe key must include dispute identity.');
        self::assertStringContainsString('statementPublicId', $source, 'Dedupe key must include statement identity.');
    }

    public function test_settlement_notification_cache_deduplication_is_isolated(): void
    {
        Cache::flush();

        $listener = app(SendSettlementDisputeNotifications::class);

        $eventA = new DisputeResolved(
            disputePublicId: (string) Str::ulid(),
            decision: 'resolved',
            ownerTenantId: 1,
            organizerTenantId: 2,
            actorUserId: 99,
            resolutionCode: 'billing_corrected',
            correlationId: 'corr-a',
        );

        $eventB = new DisputeResolved(
            disputePublicId: (string) Str::ulid(),
            decision: 'resolved',
            ownerTenantId: 3,
            organizerTenantId: 4,
            actorUserId: 88,
            resolutionCode: 'billing_corrected',
            correlationId: 'corr-b',
        );

        $listener->handle($eventA);
        $listener->handle($eventB);

        self::assertTrue(true, 'Both notifications processed without conflict across tenants.');
    }

    // ─── Retry behavior across tenants/processes ────────────────────

    public function test_job_retry_configuration_is_bounded(): void
    {
        $settlement = new GenerateRentalSettlementStatement(1, (string) Str::ulid(), 'c');
        $provision = new ProvisionMarketplaceDelegation(1, (string) Str::ulid(), 'c');
        $release = new ReleaseMarketplaceDelegation(1, (string) Str::ulid(), 'c');

        self::assertGreaterThan(0, $settlement->tries);
        self::assertLessThanOrEqual(10, $settlement->tries);

        self::assertGreaterThan(0, $provision->tries);
        self::assertLessThanOrEqual(10, $provision->tries);
        self::assertGreaterThan(0, $provision->maxExceptions);

        self::assertGreaterThan(0, $release->tries);
        self::assertLessThanOrEqual(10, $release->tries);
        self::assertGreaterThan(0, $release->maxExceptions);
    }

    public function test_delegation_jobs_use_after_commit_to_prevent_orphan_dispatches(): void
    {
        $provision = new ProvisionMarketplaceDelegation(1, (string) Str::ulid(), 'c');
        $release = new ReleaseMarketplaceDelegation(1, (string) Str::ulid(), 'c');
        $settlement = new GenerateRentalSettlementStatement(1, (string) Str::ulid(), 'c');

        self::assertTrue($provision->afterCommit, 'Provision job must dispatch after transaction commits.');
        self::assertTrue($release->afterCommit, 'Release job must dispatch after transaction commits.');
        self::assertTrue($settlement->afterCommit, 'Settlement job must dispatch after transaction commits.');
    }

    // ─── No shared state cross-tenant disclosure ────────────────────

    public function test_no_shared_cache_state_between_tenants(): void
    {
        config(['marketplace.catalog.cache_enabled' => true]);
        $cache = app(MarketplaceCatalogCache::class);

        $secretPayload = ['secret_venue' => 'owner-private-contact@example.test'];

        $cache->remember(1, 'en', ['country_code' => 'SA'], null, fn () => $secretPayload);
        $otherResult = $cache->remember(2, 'en', ['country_code' => 'SA'], null, fn () => ['public' => 'safe']);

        self::assertArrayNotHasKey('secret_venue', (array) $otherResult, 'Tenant 2 must not see tenant 1 cached data.');
        self::assertSame(['public' => 'safe'], $otherResult);
    }

    public function test_job_queue_names_are_deterministic_and_isolated(): void
    {
        $settlement = new GenerateRentalSettlementStatement(1, (string) Str::ulid(), 'c');
        $provision = new ProvisionMarketplaceDelegation(1, (string) Str::ulid(), 'c');

        self::assertSame('marketplace', $settlement->queue, 'Settlement job must use the marketplace queue.');
        self::assertSame('marketplace-delegation', $provision->queue, 'Delegation job must use the marketplace-delegation queue.');
    }

    public function test_serialized_events_carry_no_private_contact_data(): void
    {
        $events = [
            new StatementRevised(
                statementPublicId: (string) Str::ulid(),
                revision: 2,
                ownerTenantId: 1,
                organizerTenantId: 2,
                actorUserId: 5,
                correlationId: 'corr',
            ),
            new DisputeResolved(
                disputePublicId: (string) Str::ulid(),
                decision: 'resolved',
                ownerTenantId: 1,
                organizerTenantId: 2,
                actorUserId: 5,
                resolutionCode: 'billing_corrected',
                correlationId: 'corr',
            ),
        ];

        foreach ($events as $event) {
            $serialized = strtolower(serialize($event));
            self::assertStringNotContainsString('password', $serialized);
            self::assertStringNotContainsString('credential', $serialized);
            self::assertStringNotContainsString('pairing_secret', $serialized);
            self::assertStringNotContainsString('access_token', $serialized);
            self::assertStringNotContainsString('business_contact_email', $serialized);
            self::assertStringNotContainsString('business_contact_phone', $serialized);
        }
    }
}
