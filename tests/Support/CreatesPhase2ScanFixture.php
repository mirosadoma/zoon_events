<?php

namespace Tests\Support;

use App\Models\User;
use App\Modules\Authorization\Infrastructure\Persistence\Models\TenantRole;
use App\Modules\Credentials\Infrastructure\Persistence\Models\Credential;
use App\Modules\Tenancy\Domain\Context\TenantContext;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\Tenant;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\TenantMembership;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait CreatesPhase2ScanFixture
{
    use BuildsTenantFixtures;

    /** @return array{fixture:array<string,mixed>,token:string,credential:Credential,membership:TenantMembership,scanner:User} */
    protected function createIssuedCredentialScanFixture(array $permissions = ['checkin.scan.submit']): array
    {
        $this->seed(PermissionCatalogSeeder::class);
        $fixture = $this->createRegistrationFixture();
        $response = $this->withHeader('Idempotency-Key', 'scan-fixture-'.Str::lower((string) Str::ulid()))
            ->postJson(
                "http://register.example.test/api/v1/public/events/{$fixture['event']->slug}/registrations",
                $this->registrationPayload($fixture),
            )->assertCreated();

        $scanner = User::factory()->create();
        $membership = TenantMembership::query()->create([
            'tenant_id' => $fixture['tenant']->id,
            'user_id' => $scanner->id,
            'status' => 'active',
            'created_by_user_id' => $fixture['actor']->id,
        ]);
        $this->grantTenantPermissions($fixture['tenant'], $membership, $permissions);

        return [
            'fixture' => $fixture,
            'token' => $response->json('data.credential.qr_payload'),
            'credential' => Credential::query()->where('event_id', $fixture['event']->id)->firstOrFail(),
            'membership' => $membership,
            'scanner' => $scanner,
        ];
    }

    /** @param list<string> $permissions */
    protected function grantTenantPermissions(Tenant $tenant, TenantMembership $membership, array $permissions): void
    {
        $role = TenantRole::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Scan role '.Str::lower((string) Str::ulid()),
            'is_system' => false,
            'created_by_user_id' => $membership->user_id,
        ]);

        $permissionIds = DB::table('permissions')->whereIn('key', $permissions)->pluck('id');
        foreach ($permissionIds as $permissionId) {
            DB::table('tenant_role_permissions')->insert([
                'tenant_id' => $tenant->id,
                'tenant_role_id' => $role->id,
                'permission_id' => $permissionId,
                'granted_by_user_id' => $membership->user_id,
                'created_at' => now(),
            ]);
        }

        DB::table('tenant_role_assignments')->insert([
            'id' => (string) Str::ulid(),
            'tenant_id' => $tenant->id,
            'tenant_membership_id' => $membership->id,
            'tenant_role_id' => $role->id,
            'granted_by_user_id' => $membership->user_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function scanContext(array $scanFixture): TenantContext
    {
        return new TenantContext(
            $scanFixture['fixture']['tenant'],
            $scanFixture['membership'],
            $scanFixture['scanner'],
        );
    }

    /** @param array{fixture:array<string,mixed>,scanner:User} $scanFixture */
    protected function actingAsScanner(array $scanFixture): User
    {
        return $this->actingAsTenantMember($scanFixture['scanner'], $scanFixture['fixture']['tenant']);
    }

    /** @param array{fixture:array<string,mixed>} $scanFixture */
    protected function scanHeaders(array $scanFixture, string $idempotencyKey = 'scan-test'): array
    {
        return array_merge($this->tenantHeaders($scanFixture['fixture']['tenant']), [
            'Idempotency-Key' => $idempotencyKey,
        ]);
    }
}
