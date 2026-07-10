<?php

namespace Tests\Feature\IdentityVerification;

use App\Modules\IdentityVerification\Domain\ValueObjects\IdentityVerificationMethod;
use App\Modules\IdentityVerification\Domain\ValueObjects\IdentityVerificationStatus;
use App\Modules\IdentityVerification\Infrastructure\Persistence\Models\IdentityBiometricArtifact;
use App\Modules\IdentityVerification\Infrastructure\Persistence\Models\IdentityVerification;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\AssertsProblemDetails;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\CreatesPhase2ScanFixture;
use Tests\Support\Phase5MySqlTestCase;

#[Group('phase-5')]
#[Group('identity-cross-tenant')]
final class CrossTenantTest extends Phase5MySqlTestCase
{
    use AssertsProblemDetails;
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use DatabaseTransactions;

    public function test_identity_controllers_reject_cross_tenant_event_and_attendee_access(): void
    {
        $this->assertMySqlConnectionIsAvailable();

        $tenantA = $this->createIssuedCredentialScanFixture([
            'identity.configure',
            'identity.review',
            'identity.data.view',
            'identity.data.manage',
        ]);
        $tenantB = $this->createIssuedCredentialScanFixture([
            'identity.configure',
            'identity.review',
            'identity.data.view',
            'identity.data.manage',
        ]);

        $eventA = (string) $tenantA['fixture']['event']->id;
        $eventB = (string) $tenantB['fixture']['event']->id;
        $attendeeA = (string) $tenantA['credential']->attendee_id;

        $verification = IdentityVerification::query()->create([
            'tenant_id' => $tenantA['fixture']['tenant']->id,
            'event_id' => $eventA,
            'attendee_id' => $attendeeA,
            'method' => IdentityVerificationMethod::FACE_CAPTURE,
            'status' => IdentityVerificationStatus::PENDING,
            'provider' => 'mock',
            'provider_reference' => 'cross-tenant-review',
        ]);

        IdentityBiometricArtifact::query()->create([
            'tenant_id' => $tenantA['fixture']['tenant']->id,
            'verification_id' => $verification->id,
            'artifact_type' => 'template',
            'storage_reference' => '{"ciphertext":"cross-tenant"}',
            'retention_until' => CarbonImmutable::now()->addDays(30),
            'created_at' => CarbonImmutable::now(),
        ]);

        $this->actingAsScanner($tenantB);
        $headersB = $this->tenantHeaders($tenantB['fixture']['tenant']);

        $this->assertProblemDetails(
            $this->getJson("/api/v1/tenant/events/{$eventA}/identity/review", $headersB),
            404,
            'resource_not_found',
        );

        $this->assertProblemDetails(
            $this->postJson(
                "/api/v1/tenant/events/{$eventA}/identity/verifications/{$verification->id}/review",
                ['decision' => 'approve'],
                array_merge($headersB, ['Idempotency-Key' => 'cross-tenant-review-'.Str::ulid()]),
            ),
            404,
            'resource_not_found',
        );

        $this->assertProblemDetails(
            $this->getJson("/api/v1/tenant/events/{$eventA}/attendees/{$attendeeA}/identity/data", $headersB),
            404,
            'resource_not_found',
        );

        $this->assertProblemDetails(
            $this->deleteJson(
                "/api/v1/tenant/events/{$eventA}/attendees/{$attendeeA}/identity/data",
                ['reason' => 'cross_tenant_attempt'],
                array_merge($headersB, ['Idempotency-Key' => 'cross-tenant-delete-'.Str::ulid()]),
            ),
            404,
            'resource_not_found',
        );

        $this->getJson("/api/v1/tenant/events/{$eventB}/identity/review", $headersB)
            ->assertOk()
            ->assertJsonPath('data', []);
    }
}
