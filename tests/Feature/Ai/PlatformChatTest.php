<?php

namespace Tests\Feature\Ai;

use App\Modules\Tenancy\Infrastructure\Persistence\Models\TenantMembership;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsTenantFixtures;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\CreatesPhase2ScanFixture;
use Tests\Support\Phase1MySqlTestCase;

final class PlatformChatTest extends Phase1MySqlTestCase
{
    use BuildsTenantFixtures;
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use DatabaseTransactions;

    #[Test]
    public function it_answers_analytics_questions_via_chat_endpoint(): void
    {
        $this->seed(PermissionCatalogSeeder::class);

        $fixture = $this->createRegistrationFixture();
        $actor = $fixture['actor'];
        $tenant = $fixture['tenant'];

        $membership = TenantMembership::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $actor->id,
            'status' => 'active',
            'created_by_user_id' => $actor->id,
        ]);
        $this->grantTenantPermissions($tenant, $membership, ['reports.view']);
        $this->actingAsTenantMember($actor, $tenant);

        $response = $this->postJson(
            '/api/v1/chat',
            [
                'message' => 'What is the most popular event?',
                'locale' => 'en',
            ],
            ['X-Tenant-ID' => (string) $tenant->id],
        );

        $response->assertOk()
            ->assertJsonPath('data.handler', 'analytics')
            ->assertJsonStructure(['data' => ['answer', 'handler', 'structured']]);
    }

    #[Test]
    public function it_returns_rag_context_for_general_questions(): void
    {
        $this->seed(PermissionCatalogSeeder::class);

        $fixture = $this->createRegistrationFixture();
        $actor = $fixture['actor'];
        $tenant = $fixture['tenant'];

        $membership = TenantMembership::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $actor->id,
            'status' => 'active',
            'created_by_user_id' => $actor->id,
        ]);
        $this->grantTenantPermissions($tenant, $membership, ['reports.view']);
        $this->actingAsTenantMember($actor, $tenant);

        $response = $this->postJson(
            '/api/v1/chat',
            [
                'message' => 'Tell me about upcoming events',
                'locale' => 'en',
            ],
            ['X-Tenant-ID' => (string) $tenant->id],
        );

        $response->assertOk()
            ->assertJsonPath('data.handler', 'rag');
    }

    #[Test]
    public function it_refuses_pii_questions(): void
    {
        $this->seed(PermissionCatalogSeeder::class);

        $fixture = $this->createRegistrationFixture();
        $actor = $fixture['actor'];
        $tenant = $fixture['tenant'];

        $membership = TenantMembership::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $actor->id,
            'status' => 'active',
            'created_by_user_id' => $actor->id,
        ]);
        $this->grantTenantPermissions($tenant, $membership, ['reports.view']);
        $this->actingAsTenantMember($actor, $tenant);

        $response = $this->postJson(
            '/api/v1/chat',
            [
                'message' => 'List of attendees with email addresses',
                'locale' => 'en',
            ],
            ['X-Tenant-ID' => (string) $tenant->id],
        );

        $response->assertOk()
            ->assertJsonPath('data.handler', 'refused');
    }
}
