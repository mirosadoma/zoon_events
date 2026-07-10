<?php

namespace Tests\Feature\IdentityVerification;

use App\Modules\Audit\Infrastructure\Persistence\Models\AuditLog;
use App\Modules\Credentials\Infrastructure\Persistence\Models\Credential;
use App\Modules\IdentityVerification\Domain\ValueObjects\IdentityVerificationMethod;
use App\Modules\IdentityVerification\Domain\ValueObjects\IdentityVerificationStatus;
use App\Modules\IdentityVerification\Infrastructure\Persistence\Models\IdentityVerification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\AssertsProblemDetails;
use Tests\Support\CreatesIdentityAttendeeFixture;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\CreatesPhase2ScanFixture;
use Tests\Support\Phase5MySqlTestCase;

#[Group('phase-5')]
#[Group('identity-review')]
final class ReviewTest extends Phase5MySqlTestCase
{
    use AssertsProblemDetails;
    use CreatesIdentityAttendeeFixture;
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use DatabaseTransactions;

    public function test_review_endpoints_require_identity_review_permission(): void
    {
        $this->assertMySqlConnectionIsAvailable();
        $scan = $this->createIssuedCredentialScanFixture(['checkin.scan.submit']);
        $eventId = (string) $scan['fixture']['event']->id;
        $verification = $this->pendingVerification($scan);
        $indexUrl = "/api/v1/tenant/events/{$eventId}/identity/review";
        $reviewUrl = "/api/v1/tenant/events/{$eventId}/identity/verifications/{$verification->id}/review";

        $this->actingAsScanner($scan);

        $this->assertProblemDetails(
            $this->getJson($indexUrl, $this->tenantHeaders($scan['fixture']['tenant'])),
            403,
            'forbidden',
        );

        $this->assertProblemDetails(
            $this->postJson(
                $reviewUrl,
                ['decision' => 'approve'],
                array_merge($this->tenantHeaders($scan['fixture']['tenant']), ['Idempotency-Key' => 'review-no-perm-'.Str::ulid()]),
            ),
            403,
            'forbidden',
        );
    }

    public function test_reviewer_can_approve_and_reject_with_required_reason_and_audit(): void
    {
        $this->assertMySqlConnectionIsAvailable();
        $scan = $this->createIssuedCredentialScanFixture(['identity.review']);
        $eventId = (string) $scan['fixture']['event']->id;
        $verification = $this->pendingVerification($scan);
        $reviewUrl = "/api/v1/tenant/events/{$eventId}/identity/verifications/{$verification->id}/review";

        $this->actingAsScanner($scan);

        $this->postJson(
            $reviewUrl,
            ['decision' => 'reject'],
            array_merge($this->tenantHeaders($scan['fixture']['tenant']), ['Idempotency-Key' => 'review-reject-missing-'.Str::ulid()]),
        )->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);

        $this->postJson(
            $reviewUrl,
            ['decision' => 'reject', 'reason' => 'poor_capture_quality'],
            array_merge($this->tenantHeaders($scan['fixture']['tenant']), ['Idempotency-Key' => 'review-reject-'.Str::ulid()]),
        )->assertOk()
            ->assertJsonPath('data.status', IdentityVerificationStatus::REJECTED);

        self::assertTrue(
            AuditLog::query()->where('action', 'identity_review.rejected')->exists(),
        );

        $verification->refresh();
        $verification->forceFill(['status' => IdentityVerificationStatus::PENDING, 'rejection_reason' => null])->save();

        $this->postJson(
            $reviewUrl,
            ['decision' => 'approve'],
            array_merge($this->tenantHeaders($scan['fixture']['tenant']), ['Idempotency-Key' => 'review-approve-'.Str::ulid()]),
        )->assertOk()
            ->assertJsonPath('data.status', IdentityVerificationStatus::FACE_VERIFIED);

        self::assertTrue(
            AuditLog::query()->where('action', 'identity_review.approved')->exists(),
        );
    }

    /** @param array{fixture:array<string,mixed>,credential:Credential} $scan */
    private function pendingVerification(array $scan): IdentityVerification
    {
        return IdentityVerification::query()->create([
            'tenant_id' => $scan['fixture']['tenant']->id,
            'event_id' => $scan['fixture']['event']->id,
            'attendee_id' => $scan['credential']->attendee_id,
            'method' => IdentityVerificationMethod::FACE_CAPTURE,
            'status' => IdentityVerificationStatus::PENDING,
            'provider' => 'mock',
            'provider_reference' => 'face-review-test',
        ]);
    }
}
