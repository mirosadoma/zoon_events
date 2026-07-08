<?php

namespace Tests\Feature\IdentityVerification;

use App\Modules\Audit\Infrastructure\Persistence\Models\AuditLog;
use App\Modules\IdentityVerification\Application\Actions\PurgeExpiredIdentityArtifacts;
use App\Modules\IdentityVerification\Domain\ValueObjects\IdentityVerificationMethod;
use App\Modules\IdentityVerification\Domain\ValueObjects\IdentityVerificationStatus;
use App\Modules\IdentityVerification\Infrastructure\Persistence\Models\IdentityBiometricArtifact;
use App\Modules\IdentityVerification\Infrastructure\Persistence\Models\IdentityVerification;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\CreatesPhase2ScanFixture;
use Tests\Support\Phase5MySqlTestCase;

#[Group('phase-5')]
#[Group('identity-retention')]
final class RetentionPurgeTest extends Phase5MySqlTestCase
{
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use DatabaseTransactions;

    public function test_retention_purge_removes_expired_sensitive_artifacts_and_preserves_metadata(): void
    {
        $this->assertMySqlConnectionIsAvailable();
        $scan = $this->createIssuedCredentialScanFixture(['identity.review']);
        $verification = IdentityVerification::query()->create([
            'tenant_id' => $scan['fixture']['tenant']->id,
            'event_id' => $scan['fixture']['event']->id,
            'attendee_id' => $scan['credential']->attendee_id,
            'method' => IdentityVerificationMethod::FACE_CAPTURE,
            'status' => IdentityVerificationStatus::FACE_VERIFIED,
            'provider' => 'mock',
            'provider_reference' => 'face-retention-test',
            'verified_name' => 'Retention Subject',
            'verified_nationality' => 'SA',
            'verified_at' => CarbonImmutable::now()->subDays(10),
            'retention_until' => CarbonImmutable::now()->subDay(),
        ]);

        $artifact = IdentityBiometricArtifact::query()->create([
            'tenant_id' => $scan['fixture']['tenant']->id,
            'verification_id' => $verification->id,
            'artifact_type' => 'template',
            'storage_reference' => '{"ciphertext":"secret-template"}',
            'liveness_result' => 'passed',
            'retention_until' => CarbonImmutable::now()->subHour(),
            'created_at' => CarbonImmutable::now()->subDays(2),
        ]);

        $result = app(PurgeExpiredIdentityArtifacts::class)->execute(CarbonImmutable::now());

        self::assertSame(1, $result['artifact_count']);
        self::assertSame(1, $result['verification_count']);

        $artifact->refresh();
        self::assertSame('purged', $artifact->storage_reference);
        self::assertNotNull($artifact->purged_at);

        $verification->refresh();
        self::assertSame(IdentityVerificationStatus::FACE_VERIFIED, $verification->status);
        self::assertSame(IdentityVerificationMethod::FACE_CAPTURE, $verification->method);
        self::assertNull($verification->provider_reference);
        self::assertNull($verification->verified_name);
        self::assertNull($verification->verified_nationality);

        self::assertTrue(
            AuditLog::query()->where('action', 'identity_data.purged')->exists(),
        );
    }
}
