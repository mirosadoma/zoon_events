<?php

namespace Tests\Integration\MySql;

use App\Models\User;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\AssertsTenantSchema;
use Tests\Support\MySqlTestCase;

#[Group('tenant-isolation')]
class TenantSchemaTest extends MySqlTestCase
{
    use AssertsTenantSchema;
    use RefreshDatabase;

    #[Test]
    public function tenant_owned_tables_require_non_null_tenant_id(): void
    {
        $this->assertMySqlConnectionIsAvailable();

        $this->assertTenantOwnedTablesRequireTenantId([
            'tenant_memberships',
            'tenant_roles',
            'tenant_role_permissions',
            'tenant_role_assignments',
            'tenant_configurations',
            'feature_flag_overrides',
        ]);
    }

    #[Test]
    public function tenant_first_unique_constraints_scope_records_per_tenant(): void
    {
        $this->assertMySqlConnectionIsAvailable();

        $this->assertUniqueConstraintStartsWithTenantId('tenant_memberships', 'tenant_memberships_tenant_id_user_id_unique');
        $this->assertUniqueConstraintStartsWithTenantId('tenant_roles', 'tenant_roles_tenant_id_name_unique');
        $this->assertUniqueConstraintStartsWithTenantId('tenant_configurations', 'tenant_configurations_tenant_id_key_unique');
        $this->assertUniqueConstraintStartsWithTenantId('feature_flag_overrides', 'feature_flag_overrides_tenant_id_feature_flag_id_unique');
    }

    #[Test]
    public function lifecycle_tables_define_status_alignment_checks(): void
    {
        $this->assertMySqlConnectionIsAvailable();

        foreach (['tenants', 'users', 'tenant_memberships'] as $table) {
            $this->assertCheckConstraintExists($table, "{$table}_lifecycle_chk");
        }
    }

    #[Test]
    public function tenant_roles_use_tenant_first_list_index(): void
    {
        $this->assertMySqlConnectionIsAvailable();

        $this->assertIndexExists('tenant_roles', 'tenant_roles_tenant_id_created_at_id_index');
    }

    #[Test]
    public function identical_role_names_are_allowed_across_different_tenants(): void
    {
        $this->assertMySqlConnectionIsAvailable();

        $creator = User::factory()->create();
        $tenantA = Tenant::factory()->create(['created_by_user_id' => $creator->id]);
        $tenantB = Tenant::factory()->create(['created_by_user_id' => $creator->id]);

        DB::table('tenant_roles')->insert([
            [
                'tenant_id' => $tenantA->id,
                'name' => 'Operations',
                'description' => null,
                'is_system' => false,
                'created_by_user_id' => $creator->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'tenant_id' => $tenantB->id,
                'name' => 'Operations',
                'description' => null,
                'is_system' => false,
                'created_by_user_id' => $creator->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        self::assertSame(2, DB::table('tenant_roles')->where('name', 'Operations')->count());
    }

    #[Test]
    public function duplicate_role_names_within_the_same_tenant_are_rejected(): void
    {
        $this->assertMySqlConnectionIsAvailable();

        $creator = User::factory()->create();
        $tenant = Tenant::factory()->create(['created_by_user_id' => $creator->id]);

        DB::table('tenant_roles')->insert([
            'tenant_id' => $tenant->id,
            'name' => 'Finance',
            'description' => null,
            'is_system' => false,
            'created_by_user_id' => $creator->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('tenant_roles')->insert([
            'tenant_id' => $tenant->id,
            'name' => 'Finance',
            'description' => null,
            'is_system' => false,
            'created_by_user_id' => $creator->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
