<?php

namespace Tests\Integration\Security;

use App\Exceptions\FoundationException;
use App\Modules\Authorization\Infrastructure\Persistence\Models\TenantRole;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\AssertsProblemDetails;
use Tests\Support\BuildsTenantFixtures;
use Tests\TestCase;

#[Group('tenant-isolation')]
class TenantPersistenceIsolationTest extends TestCase
{
    use AssertsProblemDetails;
    use BuildsTenantFixtures;
    use RefreshDatabase;

    #[Test]
    public function scoped_queries_fail_closed_without_tenant_context(): void
    {
        $this->expectException(FoundationException::class);
        $this->expectExceptionMessage('A trusted tenant context is required');

        TenantRole::query()->get();
    }

    #[Test]
    public function scoped_queries_return_only_current_tenant_records(): void
    {
        $first = $this->createTenantMember();
        $second = $this->createTenantMember();
        $creator = $first['user'];

        $roleA = TenantRole::query()->withoutGlobalScopes()->create([
            'tenant_id' => $first['tenant']->id,
            'name' => 'Tenant A Role',
            'description' => null,
            'is_system' => false,
            'created_by_user_id' => $creator->id,
        ]);

        TenantRole::query()->withoutGlobalScopes()->create([
            'tenant_id' => $second['tenant']->id,
            'name' => 'Tenant B Role',
            'description' => null,
            'is_system' => false,
            'created_by_user_id' => $creator->id,
        ]);

        app(TenantContextStore::class)->bind($first['tenant'], $first['membership'], $first['user']);

        $roles = TenantRole::query()->get();

        self::assertCount(1, $roles);
        self::assertSame($roleA->id, $roles->first()->id);
    }

    #[Test]
    public function route_binding_hides_foreign_tenant_targets_as_not_found(): void
    {
        $member = $this->createTenantMember();
        $other = $this->createTenantMember();

        $foreignRole = TenantRole::query()->withoutGlobalScopes()->create([
            'tenant_id' => $other['tenant']->id,
            'name' => 'Foreign Role',
            'description' => null,
            'is_system' => false,
            'created_by_user_id' => $member['user']->id,
        ]);

        $this->actingAsTenantMember($member['user'], $member['tenant']);

        $response = $this->getJson(
            '/api/v1/tenant/__probe/roles/'.$foreignRole->id,
            $this->tenantHeaders($member['tenant']),
        );

        $this->assertProblemDetails($response, 404, 'resource_not_found');
    }

    #[Test]
    public function route_binding_hides_random_ids_as_not_found(): void
    {
        $member = $this->createTenantMember();
        $this->actingAsTenantMember($member['user'], $member['tenant']);

        $response = $this->getJson(
            '/api/v1/tenant/__probe/roles/01JZZZZZZZZZZZZZZZZZZZZZZZ',
            $this->tenantHeaders($member['tenant']),
        );

        $this->assertProblemDetails($response, 404, 'resource_not_found');
    }
}
