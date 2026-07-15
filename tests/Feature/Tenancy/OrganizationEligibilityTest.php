<?php

namespace Tests\Feature\Tenancy;

use App\Modules\Tenancy\Application\Contracts\OrganizationEligibility;
use App\Modules\Tenancy\Domain\OrganizationType;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrganizationEligibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_and_new_tenants_default_to_organizer(): void
    {
        self::assertTrue(Schema::hasColumn('tenants', 'organization_type'));

        $tenantId = DB::table('tenants')->insertGetId([
            'name' => 'Existing Organizer',
            'slug' => 'existing-organizer',
            'status' => 'active',
            'default_locale' => 'en',
            'timezone' => 'UTC',
            'data_residency_region' => 'ksa-central',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        self::assertSame('organizer', DB::table('tenants')->find($tenantId)->organization_type);
    }

    public function test_invalid_organization_type_is_rejected_by_the_database(): void
    {
        $this->expectException(\Throwable::class);

        Tenant::factory()->create(['organization_type' => 'invalid']);
    }

    public function test_organization_types_have_independent_eligibility(): void
    {
        $service = app(OrganizationEligibility::class);

        $organizer = Tenant::factory()->create(['organization_type' => OrganizationType::Organizer]);
        $owner = Tenant::factory()->create(['organization_type' => OrganizationType::VenueOwner]);
        $hybrid = Tenant::factory()->create(['organization_type' => OrganizationType::Hybrid]);

        self::assertTrue($service->check($organizer->id, OrganizationEligibility::REQUEST_RENTALS)->eligible);
        self::assertFalse($service->check($organizer->id, OrganizationEligibility::OWN_VENUES)->eligible);
        self::assertTrue($service->check($owner->id, OrganizationEligibility::OWN_VENUES)->eligible);
        self::assertFalse($service->check($owner->id, OrganizationEligibility::REQUEST_RENTALS)->eligible);
        self::assertTrue($service->check($hybrid->id, OrganizationEligibility::OWN_VENUES)->eligible);
        self::assertTrue($service->check($hybrid->id, OrganizationEligibility::REQUEST_RENTALS)->eligible);
    }

    public function test_inactive_and_unknown_tenants_are_denied_without_trusting_request_input(): void
    {
        $service = app(OrganizationEligibility::class);
        $tenant = Tenant::factory()->create([
            'organization_type' => OrganizationType::Hybrid,
            'status' => 'suspended',
            'suspended_at' => now(),
        ]);

        self::assertSame('tenant_inactive', $service->check($tenant->id, OrganizationEligibility::OWN_VENUES)->reason);
        self::assertSame('tenant_not_found', $service->check(PHP_INT_MAX, OrganizationEligibility::OWN_VENUES)->reason);
    }

    public function test_eligibility_is_identical_in_saas_and_on_premise_modes(): void
    {
        $tenant = Tenant::factory()->create(['organization_type' => OrganizationType::Hybrid]);

        foreach (['saas', 'on_premise'] as $mode) {
            config(['zonetec.deployment_mode' => $mode]);
            self::assertTrue(app(OrganizationEligibility::class)
                ->check($tenant->id, OrganizationEligibility::OWN_VENUES)->eligible);
        }
    }
}
