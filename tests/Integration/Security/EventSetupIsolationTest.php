<?php

namespace Tests\Integration\Security;

use App\Models\User;
use App\Modules\Audit\Application\Listeners\Phase1\Phase1AuditMapping;
use App\Modules\Events\Contracts\PublicEventContextResolver;
use App\Modules\Events\Domain\Events\EventPublished;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Route;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\Phase1MySqlTestCase;

#[Group('phase-1')]
final class EventSetupIsolationTest extends Phase1MySqlTestCase
{
    use CreatesPhase1RegistrationFixture;
    use DatabaseTransactions;

    public function test_event_lookup_requires_both_tenant_and_random_identifier(): void
    {
        $actor = User::factory()->create();
        $tenantA = Tenant::factory()->create(['created_by_user_id' => $actor->id]);
        $tenantB = Tenant::factory()->create(['created_by_user_id' => $actor->id]);
        $event = Event::query()->create([
            'tenant_id' => $tenantA->id,
            'slug' => 'synthetic-event',
            'name_en' => 'Synthetic',
            'name_ar' => 'تجريبي',
            'tier' => 'public',
            'status' => 'draft',
            'timezone' => 'Africa/Cairo',
            'start_at' => '2027-01-10 12:00:00',
            'end_at' => '2027-01-10 18:00:00',
            'registration_opens_at' => '2027-01-01 00:00:00',
            'registration_closes_at' => '2027-01-10 11:00:00',
            'capacity' => 10,
            'created_by_user_id' => $actor->id,
        ]);

        self::assertNull(Event::query()->where('tenant_id', $tenantB->id)->find($event->id));
        self::assertNotNull(Event::query()->where('tenant_id', $tenantA->id)->find($event->id));
        $this->assertSyntheticFixture($event->toArray());
    }

    public function test_every_organizer_event_route_has_tenant_context_and_least_privilege_permission(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn (Route $route): bool => str_starts_with($route->uri(), 'api/v1/tenant/events'));

        self::assertNotEmpty($routes);
        foreach ($routes as $route) {
            self::assertContains('tenant.context', $route->gatherMiddleware());
            if (str_contains($route->uri(), '/acs/')) {
                continue;
            }
            self::assertTrue(collect($route->gatherMiddleware())->contains(
                fn (string $middleware): bool => str_starts_with($middleware, 'permission:'),
            ));
        }
    }

    public function test_domain_audit_mapping_contains_identifiers_but_no_personal_or_branding_payload(): void
    {
        $mapped = (new Phase1AuditMapping)->for(new EventPublished(
            '01SYNTHETICTENANT000000000',
            '01SYNTHETICEVENT0000000000',
            '01SYNTHETICACTOR0000000000',
        ));

        self::assertSame('event.published', $mapped['action']);
        self::assertSame([], $mapped['metadata']);
        self::assertArrayNotHasKey('actor_id', $mapped);
        self::assertArrayNotHasKey('name', $mapped);
    }

    public function test_ambiguous_cross_tenant_host_and_slug_mapping_fails_closed(): void
    {
        $first = $this->createRegistrationFixture(domainReference: 'ambiguous.example.test');
        $second = $this->createRegistrationFixture(domainReference: 'second.example.test');
        $second['event']->forceFill(['slug' => $first['event']->slug])->save();
        $second['event']->branding()->update(['domain_reference' => 'ambiguous.example.test']);

        self::assertNull(app(PublicEventContextResolver::class)->resolve(
            'ambiguous.example.test',
            $first['event']->slug,
        ));
    }
}
