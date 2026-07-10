<?php

namespace Tests\Unit\Tenancy;

use App\Models\User;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsTenantFixtures;
use Tests\TestCase;

class TenantContextStoreTest extends TestCase
{
    use BuildsTenantFixtures;
    use RefreshDatabase;

    #[Test]
    public function bind_rejects_rebinding_within_the_same_request(): void
    {
        $store = app(TenantContextStore::class);
        ['user' => $user, 'tenant' => $tenant, 'membership' => $membership] = $this->createTenantMember();

        $store->bind($tenant, $membership, $user);

        $this->expectExceptionMessage('Tenant context is already bound');

        $store->bind($tenant, $membership, $user);
    }

    #[Test]
    public function bind_rejects_mismatched_membership_and_tenant(): void
    {
        $store = app(TenantContextStore::class);
        $first = $this->createTenantMember();
        $second = $this->createTenantMember();

        $this->expectExceptionMessage('Tenant membership does not belong to the resolved tenant');

        $store->bind($first['tenant'], $second['membership'], $first['user']);
    }

    #[Test]
    public function bind_rejects_mismatched_actor_and_membership(): void
    {
        $store = app(TenantContextStore::class);
        ['tenant' => $tenant, 'membership' => $membership] = $this->createTenantMember();
        $otherUser = User::factory()->create();

        $this->expectExceptionMessage('Tenant membership does not belong to the authenticated actor');

        $store->bind($tenant, $membership, $otherUser);
    }

    #[Test]
    public function current_throws_when_context_is_unset(): void
    {
        $store = app(TenantContextStore::class);

        $this->expectExceptionMessage('A trusted tenant context is required');

        $store->current();
    }

    #[Test]
    public function clear_removes_bound_context(): void
    {
        $store = app(TenantContextStore::class);
        ['user' => $user, 'tenant' => $tenant, 'membership' => $membership] = $this->createTenantMember();

        $store->bind($tenant, $membership, $user);
        $store->clear();

        self::assertNull($store->currentOrNull());
    }
}
