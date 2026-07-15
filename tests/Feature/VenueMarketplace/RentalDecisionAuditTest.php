<?php

namespace Tests\Feature\VenueMarketplace;

use App\Modules\Audit\Infrastructure\Persistence\Models\AuditLog;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use App\Modules\VenueMarketplace\Application\Actions\ApproveRentalAction;
use App\Modules\VenueMarketplace\Application\Actions\CancelRentalAction;
use App\Modules\VenueMarketplace\Application\Actions\RejectRentalAction;
use App\Modules\VenueMarketplace\Application\Actions\RevokeRentalAction;
use App\Modules\VenueMarketplace\Application\Audit\MarketplaceAuditEvent;
use App\Modules\VenueMarketplace\Domain\Exceptions\MarketplaceDomainException;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\RentalRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\Support\CreatesMarketplaceFixture;
use Tests\TestCase;

final class RentalDecisionAuditTest extends TestCase
{
    use CreatesMarketplaceFixture;
    use RefreshDatabase;

    public function test_approve_writes_correlated_owner_and_organizer_audit_rows(): void
    {
        [$owner, , $rental] = $this->submittedRental('audit-approve');

        app(ApproveRentalAction::class)->execute(
            (int) $owner['tenant']->id,
            (int) $owner['user']->id,
            $rental->public_id,
            1,
            'audit-approve-idempotency',
            'audit-approve-correlation',
        );

        $logs = AuditLog::query()->where('action', 'rental.approved')->get();
        self::assertCount(2, $logs);

        $scopes = $logs->pluck('metadata.marketplace_scope')->sort()->values()->all();
        self::assertSame(['organizer', 'owner'], $scopes);

        $correlationIds = $logs->pluck('metadata.correlation_id')->unique();
        self::assertCount(1, $correlationIds, 'Both audit rows must share the same correlation ID.');
        self::assertSame('audit-approve-correlation', $correlationIds->first());

        $this->assertAuditPayloadSanitized($logs);
    }

    public function test_reject_writes_correlated_audit_with_reason_code(): void
    {
        [$owner, , $rental] = $this->submittedRental('audit-reject');

        app(RejectRentalAction::class)->execute(
            (int) $owner['tenant']->id,
            (int) $owner['user']->id,
            $rental->public_id,
            'Asset is under maintenance',
            1,
            'audit-reject-correlation',
        );

        $logs = AuditLog::query()->where('action', 'rental.rejected')->get();
        self::assertCount(2, $logs);

        foreach ($logs as $log) {
            self::assertSame('succeeded', $log->outcome);
            self::assertSame('owner_rejected', $log->reason_code);
        }

        $this->assertAuditPayloadSanitized($logs);
    }

    public function test_cancel_writes_correlated_audit_rows_for_both_participants(): void
    {
        [, $organizer, $rental] = $this->submittedRental('audit-cancel');

        app(CancelRentalAction::class)->execute(
            (int) $organizer['tenant']->id,
            (int) $organizer['user']->id,
            $rental->public_id,
            1,
            'audit-cancel-correlation',
        );

        $logs = AuditLog::query()->where('action', 'rental.cancelled')->get();
        self::assertCount(2, $logs);

        $scopes = $logs->pluck('metadata.marketplace_scope')->sort()->values()->all();
        self::assertSame(['organizer', 'owner'], $scopes);

        foreach ($logs as $log) {
            self::assertSame('succeeded', $log->outcome);
            self::assertSame('organizer_cancelled', $log->reason_code);
        }
    }

    public function test_revoke_writes_correlated_audit_with_reason_code(): void
    {
        [$owner, , $rental] = $this->submittedRental('audit-revoke');

        app(ApproveRentalAction::class)->execute(
            (int) $owner['tenant']->id,
            (int) $owner['user']->id,
            $rental->public_id,
            1,
            'audit-revoke-approve-idempotency',
            'audit-revoke-approve-correlation',
        );

        app(RevokeRentalAction::class)->execute(
            (int) $owner['tenant']->id,
            (int) $owner['user']->id,
            $rental->public_id,
            'Safety shutdown required',
            2,
            'audit-revoke-correlation',
        );

        $logs = AuditLog::query()->where('action', 'rental.revoked')->get();
        self::assertCount(2, $logs);

        foreach ($logs as $log) {
            self::assertSame('succeeded', $log->outcome);
            self::assertSame('owner_revoked', $log->reason_code);
        }

        $this->assertAuditPayloadSanitized($logs);
    }

    public function test_audit_payload_rejects_forbidden_key_fragments(): void
    {
        foreach ([
            ['decision_reason' => 'leaked'],
            ['password_hash' => 'leaked'],
            ['token_value' => 'x'],
            ['nested' => ['credential_key' => 'leaked']],
        ] as $payload) {
            try {
                new MarketplaceAuditEvent(
                    'rental.test',
                    'owner',
                    'succeeded',
                    'test-correlation',
                    'test-public-id',
                    $payload,
                );
                self::fail('Expected forbidden key fragment to be rejected: '.json_encode($payload));
            } catch (InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }

    public function test_owner_only_read_audit_does_not_leak_to_organizer(): void
    {
        [$owner, , $rental] = $this->submittedRental('audit-read');

        $logs = AuditLog::query()->where('action', 'rental.approved')->get();
        $ownerLogs = $logs->filter(
            fn (AuditLog $log): bool => ($log->metadata['marketplace_scope'] ?? '') === 'owner'
        );
        $organizerLogs = $logs->filter(
            fn (AuditLog $log): bool => ($log->metadata['marketplace_scope'] ?? '') === 'organizer'
        );

        self::assertSame($ownerLogs->count(), $organizerLogs->count(),
            'Read operations do not generate separate audit rows in this scope.');
    }

    public function test_business_and_audit_writes_share_one_transaction(): void
    {
        [$owner, , $rental] = $this->submittedRental('audit-transaction');

        $preAuditCount = AuditLog::query()->count();
        $preRentalVersion = (int) $rental->version;

        app(ApproveRentalAction::class)->execute(
            (int) $owner['tenant']->id,
            (int) $owner['user']->id,
            $rental->public_id,
            1,
            'audit-txn-idempotency',
            'audit-txn-correlation',
        );

        $postAuditCount = AuditLog::query()->count();
        $postRentalVersion = (int) $rental->fresh()->version;

        self::assertGreaterThan($preAuditCount, $postAuditCount,
            'Audit rows must be written atomically with the business write.');
        self::assertSame($preRentalVersion + 1, $postRentalVersion,
            'Business version must advance in the same transaction.');
    }

    public function test_failed_approval_writes_no_audit_claiming_success(): void
    {
        [$owner, $organizer, $rentalA, $publicationPublicId, $event] = $this->submittedRental('audit-fail');
        $rentalB = $this->createSubmittedMarketplaceRental(
            $organizer,
            $event,
            [$publicationPublicId],
            'audit-fail-second',
        );

        app(ApproveRentalAction::class)->execute(
            (int) $owner['tenant']->id,
            (int) $owner['user']->id,
            $rentalA->public_id,
            1,
            'audit-fail-winner-idempotency',
            'audit-fail-winner-correlation',
        );

        $auditCountBefore = AuditLog::query()->where('action', 'rental.approved')->count();

        try {
            app(ApproveRentalAction::class)->execute(
                (int) $owner['tenant']->id,
                (int) $owner['user']->id,
                $rentalB->public_id,
                1,
                'audit-fail-loser-idempotency',
                'audit-fail-loser-correlation',
            );
            self::fail('Expected conflict.');
        } catch (MarketplaceDomainException) {
        }

        $auditCountAfter = AuditLog::query()->where('action', 'rental.approved')->count();
        self::assertSame($auditCountBefore, $auditCountAfter,
            'Failed approval must not leave any audit row claiming success.');
    }

    private function assertAuditPayloadSanitized(\Illuminate\Support\Collection $logs): void
    {
        $serialized = json_encode($logs->pluck('metadata')->all(), JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('private-fixture@example.test', $serialized);
        self::assertStringNotContainsString('opaque:', $serialized);
        self::assertStringNotContainsString('decision_reason', $serialized);
        self::assertStringNotContainsString('password', $serialized);
    }

    /**
     * @return array{0:array,1:array,2:RentalRequest,3:string,4:mixed}
     */
    private function submittedRental(string $key): array
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

        return [$owner, $organizer, $rental, $publicationPublicId, $event];
    }
}
