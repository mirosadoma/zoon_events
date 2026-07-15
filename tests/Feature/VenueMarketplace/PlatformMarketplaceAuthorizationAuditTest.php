<?php

namespace Tests\Feature\VenueMarketplace;

use App\Models\User;
use App\Modules\Authorization\Infrastructure\Persistence\Models\PlatformRole;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use App\Modules\VenueMarketplace\Application\Actions\AddMarketplaceDisputeNoteAction;
use App\Modules\VenueMarketplace\Application\Actions\CancelRentalAction;
use App\Modules\VenueMarketplace\Application\Actions\GenerateSettlementStatementAction;
use App\Modules\VenueMarketplace\Application\Actions\OpenMarketplaceDisputeAction;
use App\Modules\VenueMarketplace\Application\Actions\ResolveMarketplaceDisputeAction;
use App\Modules\VenueMarketplace\Application\Actions\ReviseSettlementStatementAction;
use App\Modules\VenueMarketplace\Application\Actions\StartMarketplaceDisputeReviewAction;
use App\Modules\VenueMarketplace\Application\Audit\MarketplaceAuditEvent;
use App\Modules\VenueMarketplace\Application\Audit\MarketplaceAuditWriter;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\MarketplaceDispute;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\SettlementStatement;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesMarketplaceFixture;
use Tests\TestCase;

final class PlatformMarketplaceAuthorizationAuditTest extends TestCase
{
    use CreatesMarketplaceFixture;
    use RefreshDatabase;

    public function test_platform_marketplace_view_grants_read_only_access(): void
    {
        $this->seed(PermissionSeeder::class);
        $platformUser = User::factory()->create();
        $this->grantPlatformPermission($platformUser, 'platform.marketplace.view');

        $this->actingAs($platformUser, 'sanctum');
        $this->getJson('/api/v1/platform/marketplace/rentals')
            ->assertSuccessful();
        $this->getJson('/api/v1/platform/marketplace/statements')
            ->assertSuccessful();
    }

    public function test_disputes_manage_permission_is_required_for_dispute_operations(): void
    {
        $this->seed(PermissionSeeder::class);
        $viewOnlyUser = User::factory()->create();
        $this->grantPlatformPermission($viewOnlyUser, 'platform.marketplace.view');

        $this->actingAs($viewOnlyUser, 'sanctum');
        $this->getJson('/api/v1/platform/marketplace/disputes')
            ->assertForbidden();
    }

    public function test_tenant_role_does_not_escalate_to_platform_marketplace(): void
    {
        $this->seed(PermissionSeeder::class);
        $fixture = $this->createTenantMember(tenantAttributes: ['organization_type' => 'venue_owner']);
        $this->grantTenantReportsPermission($fixture);

        $this->actingAs($fixture['user'], 'sanctum');
        $this->getJson('/api/v1/platform/marketplace/rentals')
            ->assertForbidden();
    }

    public function test_dispute_note_and_resolution_write_audits_for_all_scopes(): void
    {
        [$owner, $organizer, $statement, $dispute] = $this->openedDispute('audit-note');
        $platformUser = User::factory()->create();
        $auditEvents = [];

        $this->app->bind(MarketplaceAuditWriter::class, function () use (&$auditEvents) {
            return new class($auditEvents) implements MarketplaceAuditWriter {
                public function __construct(private array &$events) {}

                public function write(MarketplaceAuditEvent $event): void
                {
                    $this->events[] = $event;
                }
            };
        });

        app(AddMarketplaceDisputeNoteAction::class)->execute(
            (int) $platformUser->id,
            $dispute->public_id,
            'Investigation note.',
            'participants',
            'audit-note-idem',
            'audit-note-corr',
        );

        $noteActions = array_filter($auditEvents, fn ($e) => $e->action === 'dispute.note_added');
        $scopes = array_map(fn ($e) => $e->scope, $noteActions);
        self::assertContains('owner', $scopes);
        self::assertContains('organizer', $scopes);
        self::assertContains('platform', $scopes);
    }

    public function test_resolution_writes_audit_with_reason_code(): void
    {
        [$owner, $organizer, $statement, $dispute] = $this->openedDispute('audit-resolve');
        $platformUser = User::factory()->create();
        $auditEvents = [];

        $this->app->bind(MarketplaceAuditWriter::class, function () use (&$auditEvents) {
            return new class($auditEvents) implements MarketplaceAuditWriter {
                public function __construct(private array &$events) {}

                public function write(MarketplaceAuditEvent $event): void
                {
                    $this->events[] = $event;
                }
            };
        });

        app(ResolveMarketplaceDisputeAction::class)->execute(
            (int) $platformUser->id,
            $dispute->public_id,
            'resolve',
            'confirmed_error',
            'Error confirmed and noted.',
            'audit-resolve-idem',
            'audit-resolve-corr',
        );

        $resolvedActions = array_filter($auditEvents, fn ($e) => $e->action === 'dispute.resolved');
        self::assertNotEmpty($resolvedActions);
        foreach ($resolvedActions as $event) {
            self::assertSame('succeeded', $event->outcome);
            self::assertArrayHasKey('resolution_code', $event->payload);
        }
    }

    public function test_revision_writes_audit_for_owner_organizer_platform(): void
    {
        [$owner, $organizer, $statement, $dispute] = $this->openedDispute('audit-revise');
        $platformUser = User::factory()->create();
        $auditEvents = [];

        $this->app->bind(MarketplaceAuditWriter::class, function () use (&$auditEvents) {
            return new class($auditEvents) implements MarketplaceAuditWriter {
                public function __construct(private array &$events) {}

                public function write(MarketplaceAuditEvent $event): void
                {
                    $this->events[] = $event;
                }
            };
        });

        app(ReviseSettlementStatementAction::class)->execute(
            (int) $platformUser->id,
            $statement->public_id,
            $dispute->public_id,
            'factual_correction',
            [],
            'audit-revise-idem',
            'audit-revise-corr',
        );

        $revisionActions = array_filter($auditEvents, fn ($e) => $e->action === 'statement.revised');
        $scopes = array_map(fn ($e) => $e->scope, $revisionActions);
        self::assertContains('owner', $scopes);
        self::assertContains('organizer', $scopes);
        self::assertContains('platform', $scopes);
    }

    private function grantPlatformPermission(User $user, string $permission): void
    {
        $role = PlatformRole::query()->create([
            'name' => "Platform role for {$permission}",
            'is_system' => false,
            'created_by_user_id' => $user->id,
        ]);
        DB::table('platform_role_permissions')->insert([
            'platform_role_id' => $role->id,
            'permission_id' => DB::table('permissions')->where('key', $permission)->value('id'),
            'granted_by_user_id' => $user->id,
            'created_at' => now(),
        ]);
        DB::table('platform_role_assignments')->insert([
            'user_id' => $user->id,
            'platform_role_id' => $role->id,
            'granted_by_user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function grantTenantReportsPermission(array $fixture): void
    {
        $this->seed(PermissionSeeder::class);
        $role = \App\Modules\Authorization\Infrastructure\Persistence\Models\TenantRole::query()->create([
            'tenant_id' => $fixture['tenant']->id,
            'name' => 'Statement viewer',
            'is_system' => false,
            'created_by_user_id' => $fixture['user']->id,
        ]);
        DB::table('tenant_role_permissions')->insert([
            'tenant_id' => $fixture['tenant']->id,
            'tenant_role_id' => $role->id,
            'permission_id' => DB::table('permissions')->where('key', 'reports.view')->value('id'),
            'granted_by_user_id' => $fixture['user']->id,
            'created_at' => now(),
        ]);
        DB::table('tenant_role_assignments')->insert([
            'tenant_id' => $fixture['tenant']->id,
            'tenant_membership_id' => $fixture['membership']->id,
            'tenant_role_id' => $role->id,
            'granted_by_user_id' => $fixture['user']->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array{0:array,1:array,2:SettlementStatement,3:MarketplaceDispute}
     */
    private function openedDispute(string $key): array
    {
        $this->freezeMarketplaceClock();
        $organizer = $this->createTenantMember(tenantAttributes: ['organization_type' => 'organizer']);
        $event = $this->createMarketplaceEvent($organizer['tenant'], $organizer['user']);
        $owner = $this->createTenantMember(tenantAttributes: ['organization_type' => 'venue_owner']);
        $inventory = $this->createPublishedMarketplaceInventory($owner, $key);
        app(TenantContextStore::class)->clear();
        $pubId = $inventory['assets'][3]->publications()->where('status', 'active')->value('public_id');
        $rental = $this->createSubmittedMarketplaceRental($organizer, $event, [$pubId], "{$key}-rental");

        app(CancelRentalAction::class)->execute(
            (int) $organizer['tenant']->id,
            (int) $organizer['user']->id,
            $rental->public_id,
            1,
            "{$key}-cancel",
        );

        $statement = app(GenerateSettlementStatementAction::class)->execute(
            (int) $owner['tenant']->id,
            $rental->public_id,
            "{$key}-gen",
        );

        $dispute = app(OpenMarketplaceDisputeAction::class)->execute(
            (int) $owner['tenant']->id,
            (int) $owner['user']->id,
            $statement->public_id,
            'billing_error',
            'Incorrect billing for audit test.',
            "{$key}-dispute-idem",
            "{$key}-dispute-corr",
        );

        return [$owner, $organizer, $statement, $dispute];
    }
}
