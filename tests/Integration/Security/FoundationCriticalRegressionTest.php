<?php

namespace Tests\Integration\Security;

use App\Models\User;
use App\Modules\Audit\Application\AuditedTransaction;
use App\Modules\Audit\Application\AuditWriter;
use App\Modules\Audit\Application\Integrity\AuditIntegrityService;
use App\Modules\Audit\Infrastructure\Persistence\Models\AuditLog;
use App\Modules\Authorization\Infrastructure\Persistence\Models\TenantRole;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\Tenant;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\TenantMembership;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\TestCase;

#[Group('audit')]
#[Group('rbac')]
class FoundationCriticalRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_rejects_cross_tenant_role_assignment_composition(): void
    {
        $actor = User::factory()->create();
        $tenantA = Tenant::factory()->create(['created_by_user_id' => $actor->id]);
        $tenantB = Tenant::factory()->create(['created_by_user_id' => $actor->id]);
        $membershipA = TenantMembership::factory()->create([
            'tenant_id' => $tenantA->id,
            'user_id' => $actor->id,
            'created_by_user_id' => $actor->id,
        ]);
        $roleB = TenantRole::query()->create([
            'tenant_id' => $tenantB->id,
            'name' => 'Foreign administrator',
            'is_system' => false,
            'created_by_user_id' => $actor->id,
        ]);

        $this->expectException(QueryException::class);

        DB::table('tenant_role_assignments')->insert([
            'tenant_id' => $tenantA->id,
            'tenant_membership_id' => $membershipA->id,
            'tenant_role_id' => $roleB->id,
            'granted_by_user_id' => $actor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_audited_transaction_rolls_back_state_when_evidence_fails(): void
    {
        $actor = User::factory()->create();
        $tenant = Tenant::factory()->create([
            'created_by_user_id' => $actor->id,
            'name' => 'Before',
        ]);

        try {
            app(AuditedTransaction::class)->run(
                function () use ($tenant): Tenant {
                    $tenant->update(['name' => 'After']);

                    return $tenant;
                },
                static fn (): never => throw new RuntimeException('forced audit failure'),
            );
            self::fail('The forced audit failure was not thrown.');
        } catch (RuntimeException $exception) {
            self::assertSame('forced audit failure', $exception->getMessage());
        }

        self::assertSame('Before', $tenant->fresh()->name);
    }

    public function test_audit_records_cannot_be_updated_or_deleted_through_the_model(): void
    {
        $record = new AuditLog;
        $record->forceFill([
            'scope' => 'platform',
            'actor_type' => 'anonymous',
            'action' => 'test.recorded',
            'outcome' => 'succeeded',
            'channel' => 'system',
            'correlation_id' => 'test-correlation',
            'metadata' => [],
            'occurred_at' => now(),
            'integrity_algorithm' => 'hmac-sha256-v1',
            'integrity_key_id' => 'testing-key',
            'integrity_hash' => str_repeat('a', 64),
        ])->save();

        $record = AuditLog::query()->firstOrFail();
        $record->action = 'test.modified';

        try {
            $record->save();
            self::fail('Audit update unexpectedly succeeded.');
        } catch (LogicException) {
            self::assertTrue(true);
        }

        $this->expectException(LogicException::class);
        $record->delete();
    }

    public function test_persisted_audit_payload_verifies_and_direct_tampering_is_detected(): void
    {
        $actor = User::factory()->create();
        $record = app(AuditWriter::class)->writePlatform(
            'security.probe',
            'succeeded',
            $actor,
            targetType: 'user',
            targetId: $actor->id,
            metadata: ['safe' => true],
        )->fresh();
        $integrity = app(AuditIntegrityService::class);

        self::assertTrue($integrity->verify($record->integrityPayload(), $record->integrity_key_id, $record->integrity_hash));
        $this->artisan('zonetec:audit:verify')->assertSuccessful();

        DB::table('audit_logs')->where('id', $record->id)->update(['action' => 'security.tampered']);
        $tampered = AuditLog::query()->findOrFail($record->id);

        self::assertFalse($integrity->verify($tampered->integrityPayload(), $tampered->integrity_key_id, $tampered->integrity_hash));
        $this->artisan('zonetec:audit:verify')->assertFailed();
    }
}
