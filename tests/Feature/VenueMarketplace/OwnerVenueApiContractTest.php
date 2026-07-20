<?php

namespace Tests\Feature\VenueMarketplace;

use App\Modules\Authorization\Infrastructure\Persistence\Models\TenantRole;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsTenantFixtures;
use Tests\TestCase;

final class OwnerVenueApiContractTest extends TestCase
{
    use BuildsTenantFixtures;
    use DatabaseTransactions;

    public function test_owner_venue_contract_covers_crud_assets_availability_and_publication(): void
    {
        $fixture = $this->createTenantMember(tenantAttributes: ['organization_type' => 'venue_owner']);
        $this->grantVenueManage($fixture);
        $this->actingAsTenantMember($fixture['user'], $fixture['tenant']);
        $headers = $this->tenantHeaders($fixture['tenant']);

        $created = $this->withHeaders($headers + $this->idempotency('create-venue'))
            ->postJson('/api/v1/tenant/venues', $this->venuePayload())
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.version', 1)
            ->assertJsonStructure(['data' => ['public_id', 'name_en', 'name_ar', 'status', 'version'], 'meta']);
        $venueId = $created->json('data.public_id');

        $this->withHeaders($headers)->getJson('/api/v1/tenant/venues?page_size=10')
            ->assertOk()
            ->assertJsonPath('data.0.public_id', $venueId)
            ->assertJsonStructure(['data', 'meta' => ['page_size', 'has_more', 'next_cursor']]);
        $this->withHeaders($headers)->getJson("/api/v1/tenant/venues/{$venueId}")
            ->assertOk()->assertJsonPath('data.public_id', $venueId);

        $this->withHeaders($headers + $this->idempotency('update-venue'))
            ->patchJson("/api/v1/tenant/venues/{$venueId}", $this->venuePayload() + ['version' => 1])
            ->assertOk()->assertJsonPath('data.version', 2);
        $this->withHeaders($headers + $this->idempotency('activate-venue'))
            ->postJson("/api/v1/tenant/venues/{$venueId}/status", ['status' => 'active'])
            ->assertOk()->assertJsonPath('data.status', 'active');

        $asset = $this->withHeaders($headers + $this->idempotency('create-asset'))
            ->postJson("/api/v1/tenant/venues/{$venueId}/assets", $this->assetPayload())
            ->assertCreated()
            ->assertJsonPath('data.binding_status', 'active')
            ->assertJsonMissingPath('data.binding')
            ->assertJsonMissingPath('data.external_reference');
        $assetId = $asset->json('data.public_id');

        $this->withHeaders($headers)->getJson("/api/v1/tenant/venues/{$venueId}/assets")
            ->assertOk()->assertJsonPath('data.0.public_id', $assetId);
        $this->withHeaders($headers)->getJson("/api/v1/tenant/venues/{$venueId}/assets/{$assetId}")
            ->assertOk()->assertJsonPath('data.public_id', $assetId);

        $this->withHeaders($headers + $this->idempotency('update-asset'))
            ->patchJson(
                "/api/v1/tenant/venues/{$venueId}/assets/{$assetId}",
                $this->assetPayload() + ['version' => 1],
            )
            ->assertOk()->assertJsonPath('data.version', 2);

        $this->withHeaders($headers + $this->idempotency('availability'))
            ->putJson("/api/v1/tenant/venues/{$venueId}/assets/{$assetId}/availability", [
                'version' => 2,
                'windows' => [[
                    'available_from' => '2027-01-10T09:00:00+03:00',
                    'available_until' => '2027-01-10T18:00:00+03:00',
                    'status' => 'available',
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('data.0.status', 'available');

        $this->withHeaders($headers + $this->idempotency('publish'))
            ->postJson("/api/v1/tenant/venues/{$venueId}/assets/{$assetId}/publication")
            ->assertCreated()
            ->assertJsonPath('data.asset_public_id', $assetId)
            ->assertJsonMissingPath('data.binding');
        $this->withHeaders($headers + $this->idempotency('withdraw'))
            ->deleteJson("/api/v1/tenant/venues/{$venueId}/assets/{$assetId}/publication")
            ->assertNoContent();

        $this->withHeaders($headers + $this->idempotency('archive'))
            ->postJson("/api/v1/tenant/venues/{$venueId}/archive")
            ->assertOk()->assertJsonPath('data.status', 'archived');
    }

    public function test_authentication_idempotency_and_validation_errors_match_contract(): void
    {
        $this->getJson('/api/v1/tenant/venues')->assertUnauthorized();

        $fixture = $this->createTenantMember(tenantAttributes: ['organization_type' => 'venue_owner']);
        $this->grantVenueManage($fixture);
        $this->actingAsTenantMember($fixture['user'], $fixture['tenant']);
        $headers = $this->tenantHeaders($fixture['tenant']);

        $this->withHeaders($headers)->postJson('/api/v1/tenant/venues', $this->venuePayload())
            ->assertUnprocessable()->assertJsonPath('code', 'idempotency_key_required');
        $this->withHeaders($headers + $this->idempotency('invalid-venue'))
            ->postJson('/api/v1/tenant/venues', [])
            ->assertUnprocessable()->assertJsonPath('code', 'validation_failed');
    }

    private function venuePayload(): array
    {
        return [
            'name_en' => 'Riyadh Expo', 'name_ar' => 'معرض الرياض',
            'description_en' => 'Owner venue', 'description_ar' => 'موقع المالك',
            'address_en' => 'King Road', 'address_ar' => 'طريق الملك',
            'country_code' => 'SA', 'city_code' => 'riyadh', 'timezone' => 'Asia/Riyadh',
            'business_contact_email' => 'owner-private@example.test', 'publish_contact' => false,
        ];
    }

    private function assetPayload(): array
    {
        return [
            'asset_type' => 'kiosk', 'name_en' => 'Kiosk', 'name_ar' => 'كشك',
            'description_en' => 'Fixed kiosk', 'description_ar' => 'كشك ثابت',
            'location_en' => 'Hall A', 'location_ar' => 'القاعة أ',
            'capabilities' => ['kiosk.manage'], 'capacity_per_minute' => 10,
            'operational_status' => 'active', 'pricing_model' => 'per_hour',
            'price_minor' => 1000, 'currency' => 'SAR',
            'binding' => [
                'control_family' => 'kiosk', 'adapter_key' => 'fake',
                'external_reference' => 'opaque:kiosk:one',
            ],
        ];
    }

    private function grantVenueManage(array $fixture): void
    {
        $this->seed(PermissionSeeder::class);
        $role = TenantRole::query()->create([
            'tenant_id' => $fixture['tenant']->id,
            'name' => 'Venue API manager',
            'is_system' => false,
            'created_by_user_id' => $fixture['user']->id,
        ]);
        DB::table('tenant_role_permissions')->insert([
            'tenant_id' => $fixture['tenant']->id,
            'tenant_role_id' => $role->id,
            'permission_id' => DB::table('permissions')->where('key', 'venue.manage')->value('id'),
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

    private function idempotency(string $suffix): array
    {
        return ['Idempotency-Key' => "venue-contract-{$suffix}"];
    }
}
