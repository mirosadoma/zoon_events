<?php

namespace Tests\Feature\Tenancy;

use App\Models\User;
use App\Modules\Shared\Domain\LifecycleStatus;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\AssertsProblemDetails;
use Tests\Support\BuildsTenantFixtures;
use Tests\TestCase;

#[Group('tenant-isolation')]
class ResolveTenantContextTest extends TestCase
{
    use AssertsProblemDetails;
    use BuildsTenantFixtures;
    use RefreshDatabase;

    #[Test]
    public function missing_tenant_header_is_rejected(): void
    {
        ['user' => $user] = $this->createTenantMember();
        $this->actingAsTenantMember($user, Tenant::factory()->make());

        $response = $this->getJson('/api/v1/tenant/__probe/context');

        $this->assertProblemDetails($response, 403, 'tenant_context_required');
    }

    #[Test]
    public function malformed_tenant_header_is_rejected_without_target_disclosure(): void
    {
        ['user' => $user] = $this->createTenantMember();
        $this->actingAsTenantMember($user, Tenant::factory()->make());

        $response = $this->getJson('/api/v1/tenant/__probe/context', [
            'X-Tenant-ID' => 'not-a-valid-ulid',
        ]);

        $this->assertProblemDetails($response, 404, 'resource_not_found');
    }

    #[Test]
    public function inactive_tenant_context_is_rejected(): void
    {
        ['user' => $user, 'tenant' => $tenant] = $this->createTenantMember(
            tenantAttributes: [
                'status' => LifecycleStatus::Suspended->value,
                'suspended_at' => now(),
            ],
        );

        $this->actingAsTenantMember($user, $tenant);

        $response = $this->getJson('/api/v1/tenant/__probe/context', $this->tenantHeaders($tenant));

        $this->assertProblemDetails($response, 403, 'tenant_context_invalid');
    }

    #[Test]
    public function non_member_actor_receives_identical_not_found_response(): void
    {
        ['tenant' => $memberTenant] = $this->createTenantMember();
        $outsider = User::factory()->create();
        $this->actingAsTenantMember($outsider, $memberTenant);

        $response = $this->getJson('/api/v1/tenant/__probe/context', $this->tenantHeaders($memberTenant));

        $this->assertProblemDetails($response, 404, 'resource_not_found');
    }

    #[Test]
    public function forged_tenant_header_for_a_different_active_membership_is_rejected(): void
    {
        ['user' => $user, 'tenant' => $memberTenant] = $this->createTenantMember();
        $other = $this->createTenantMember();
        $this->actingAsTenantMember($user, $memberTenant);

        $response = $this->getJson('/api/v1/tenant/__probe/context', $this->tenantHeaders($other['tenant']));

        $this->assertProblemDetails($response, 404, 'resource_not_found');
    }

    #[Test]
    public function valid_tenant_context_resolves_for_active_member(): void
    {
        ['user' => $user, 'tenant' => $tenant] = $this->createTenantMember();
        $this->actingAsTenantMember($user, $tenant);

        $response = $this->getJson('/api/v1/tenant/__probe/context', $this->tenantHeaders($tenant));

        $response->assertOk()
            ->assertJsonPath('tenant_id', $tenant->id)
            ->assertJsonPath('membership_id', fn ($value) => is_string($value) && $value !== '');
    }

    #[Test]
    public function tenant_context_is_cleared_after_the_request_completes(): void
    {
        ['user' => $user, 'tenant' => $tenant] = $this->createTenantMember();
        $this->actingAsTenantMember($user, $tenant);

        $this->getJson('/api/v1/tenant/__probe/context', $this->tenantHeaders($tenant))->assertOk();

        $this->getJson('/api/v1/tenant/__probe/store-state')
            ->assertOk()
            ->assertJsonPath('bound', false);
    }
}
