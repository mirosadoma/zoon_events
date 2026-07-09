<?php

namespace Tests\Feature\IdentityVerification;

use App\Modules\IdentityVerification\Application\Queries\IdentityGate;
use App\Modules\IdentityVerification\Application\Support\RequirementResolver;
use App\Modules\IdentityVerification\Domain\ValueObjects\IdentityReasonCode;
use App\Modules\IdentityVerification\Domain\ValueObjects\IdentityRequirementLevel;
use App\Modules\IdentityVerification\Domain\ValueObjects\IdentityVerificationStatus;
use App\Modules\IdentityVerification\Infrastructure\Persistence\Models\IdentityVerification;
use App\Modules\IdentityVerification\Infrastructure\Persistence\Models\IdentityVerificationRequirement;
use App\Modules\Ticketing\Infrastructure\Persistence\Models\TicketType;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\CreatesPhase2ScanFixture;
use Tests\Support\Phase5MySqlTestCase;

#[Group('phase-5')]
#[Group('identity-gate')]
final class IdentityGateTest extends Phase5MySqlTestCase
{
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use DatabaseTransactions;

    public function test_requirement_resolver_prefers_ticket_override_then_event_default(): void
    {
        $this->assertMySqlConnectionIsAvailable();
        $scan = $this->createIssuedCredentialScanFixture(['credential.view']);
        $tenantId = (string) $scan['fixture']['tenant']->id;
        $eventId = (string) $scan['fixture']['event']->id;
        $ticketId = (string) $scan['fixture']['ticket']->id;

        IdentityVerificationRequirement::query()->create([
            'tenant_id' => $tenantId,
            'event_id' => $eventId,
            'ticket_type_id' => null,
            'level' => IdentityRequirementLevel::REQUIRED_BEFORE_GATE,
            'face_fallback_enabled' => true,
        ]);
        IdentityVerificationRequirement::query()->create([
            'tenant_id' => $tenantId,
            'event_id' => $eventId,
            'ticket_type_id' => $ticketId,
            'level' => IdentityRequirementLevel::REQUIRED_BEFORE_CREDENTIAL,
            'face_fallback_enabled' => true,
        ]);

        $resolver = app(RequirementResolver::class);
        self::assertSame(
            IdentityRequirementLevel::REQUIRED_BEFORE_CREDENTIAL,
            $resolver->resolve($tenantId, $eventId, $ticketId),
        );
        self::assertSame(
            IdentityRequirementLevel::REQUIRED_BEFORE_GATE,
            $resolver->resolve($tenantId, $eventId, null),
        );
    }

    public function test_identity_gate_returns_satisfied_when_requirement_is_not_required(): void
    {
        $this->assertMySqlConnectionIsAvailable();
        $scan = $this->createIssuedCredentialScanFixture(['credential.view']);
        $tenantId = (string) $scan['fixture']['tenant']->id;
        $eventId = (string) $scan['fixture']['event']->id;
        $attendeeId = (string) $scan['credential']->attendee_id;

        $result = app(IdentityGate::class)->evaluate($tenantId, $eventId, $attendeeId, 'credential');

        self::assertTrue($result->satisfied);
        self::assertSame(IdentityVerificationStatus::NOT_REQUIRED, $result->status);
        self::assertNull($result->reasonCode);
    }

    public function test_identity_gate_blocks_unverified_required_attendee_and_allows_verified(): void
    {
        $this->assertMySqlConnectionIsAvailable();
        $scan = $this->createIssuedCredentialScanFixture(['credential.view']);
        $tenantId = (string) $scan['fixture']['tenant']->id;
        $eventId = (string) $scan['fixture']['event']->id;
        $ticketId = (string) $scan['fixture']['ticket']->id;
        $attendeeId = (string) $scan['credential']->attendee_id;

        IdentityVerificationRequirement::query()->create([
            'tenant_id' => $tenantId,
            'event_id' => $eventId,
            'ticket_type_id' => $ticketId,
            'level' => IdentityRequirementLevel::REQUIRED_BEFORE_CREDENTIAL,
            'face_fallback_enabled' => true,
        ]);

        $gate = app(IdentityGate::class);
        $blocked = $gate->evaluate($tenantId, $eventId, $attendeeId, 'credential');
        self::assertFalse($blocked->satisfied);
        self::assertSame(IdentityReasonCode::NOT_VERIFIED, $blocked->reasonCode);

        IdentityVerification::query()->create([
            'tenant_id' => $tenantId,
            'event_id' => $eventId,
            'attendee_id' => $attendeeId,
            'method' => 'gov_identity',
            'status' => IdentityVerificationStatus::GOV_VERIFIED,
            'verified_name' => 'Verified Test',
            'verified_nationality' => 'SA',
            'verified_at' => now(),
        ]);

        $allowed = $gate->evaluate($tenantId, $eventId, $attendeeId, 'credential');
        self::assertTrue($allowed->satisfied);
        self::assertSame(IdentityVerificationStatus::GOV_VERIFIED, $allowed->status);
    }

    public function test_identity_gate_maps_rejected_and_expired_to_specific_reason_codes(): void
    {
        $this->assertMySqlConnectionIsAvailable();
        $scan = $this->createIssuedCredentialScanFixture(['credential.view']);
        $tenantId = (string) $scan['fixture']['tenant']->id;
        $eventId = (string) $scan['fixture']['event']->id;
        $ticketId = (string) $scan['fixture']['ticket']->id;
        $attendeeId = (string) $scan['credential']->attendee_id;

        IdentityVerificationRequirement::query()->create([
            'tenant_id' => $tenantId,
            'event_id' => $eventId,
            'ticket_type_id' => $ticketId,
            'level' => IdentityRequirementLevel::REQUIRED_BEFORE_GATE,
            'face_fallback_enabled' => false,
        ]);

        IdentityVerification::query()->create([
            'tenant_id' => $tenantId,
            'event_id' => $eventId,
            'attendee_id' => $attendeeId,
            'method' => 'manual_review',
            'status' => IdentityVerificationStatus::REJECTED,
            'rejection_reason' => 'bad_capture',
        ]);

        $gate = app(IdentityGate::class);
        $rejected = $gate->evaluate($tenantId, $eventId, $attendeeId, 'gate');
        self::assertFalse($rejected->satisfied);
        self::assertSame(IdentityReasonCode::REJECTED, $rejected->reasonCode);

        IdentityVerification::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('attendee_id', $attendeeId)
            ->update(['status' => IdentityVerificationStatus::EXPIRED, 'rejection_reason' => null]);

        $expired = $gate->evaluate($tenantId, $eventId, $attendeeId, 'gate');
        self::assertFalse($expired->satisfied);
        self::assertSame(IdentityReasonCode::EXPIRED, $expired->reasonCode);
    }

    public function test_required_vip_only_blocks_matching_vip_attendees_at_credential_and_gate(): void
    {
        $this->assertMySqlConnectionIsAvailable();
        $scan = $this->createIssuedCredentialScanFixture(['credential.view']);
        $tenantId = (string) $scan['fixture']['tenant']->id;
        $eventId = (string) $scan['fixture']['event']->id;
        $ticketId = (string) $scan['fixture']['ticket']->id;
        $attendeeId = (string) $scan['credential']->attendee_id;

        IdentityVerificationRequirement::query()->create([
            'tenant_id' => $tenantId,
            'event_id' => $eventId,
            'ticket_type_id' => null,
            'level' => IdentityRequirementLevel::REQUIRED_VIP,
            'face_fallback_enabled' => true,
        ]);

        $gate = app(IdentityGate::class);
        $general = $gate->evaluate($tenantId, $eventId, $attendeeId, 'credential');
        self::assertTrue($general->satisfied);
        self::assertSame(IdentityVerificationStatus::NOT_REQUIRED, $general->status);

        $generalAtGate = $gate->evaluate($tenantId, $eventId, $attendeeId, 'gate');
        self::assertTrue($generalAtGate->satisfied);

        TicketType::query()
            ->where('id', $ticketId)
            ->update(['attendee_type' => 'vip']);

        $blocked = $gate->evaluate($tenantId, $eventId, $attendeeId, 'credential');
        self::assertFalse($blocked->satisfied);
        self::assertSame(IdentityReasonCode::NOT_VERIFIED, $blocked->reasonCode);

        $blockedAtGate = $gate->evaluate($tenantId, $eventId, $attendeeId, 'gate');
        self::assertFalse($blockedAtGate->satisfied);
        self::assertSame(IdentityReasonCode::NOT_VERIFIED, $blockedAtGate->reasonCode);
    }

    public function test_required_vvip_only_blocks_matching_vvip_attendees(): void
    {
        $this->assertMySqlConnectionIsAvailable();
        $scan = $this->createIssuedCredentialScanFixture(['credential.view']);
        $tenantId = (string) $scan['fixture']['tenant']->id;
        $eventId = (string) $scan['fixture']['event']->id;
        $ticketId = (string) $scan['fixture']['ticket']->id;
        $attendeeId = (string) $scan['credential']->attendee_id;

        IdentityVerificationRequirement::query()->create([
            'tenant_id' => $tenantId,
            'event_id' => $eventId,
            'ticket_type_id' => null,
            'level' => IdentityRequirementLevel::REQUIRED_VVIP,
            'face_fallback_enabled' => true,
        ]);

        TicketType::query()
            ->where('id', $ticketId)
            ->update(['attendee_type' => 'vip']);

        $gate = app(IdentityGate::class);
        $vipNotVvip = $gate->evaluate($tenantId, $eventId, $attendeeId, 'gate');
        self::assertTrue($vipNotVvip->satisfied);

        TicketType::query()
            ->where('id', $ticketId)
            ->update(['attendee_type' => 'vvip']);

        $blocked = $gate->evaluate($tenantId, $eventId, $attendeeId, 'gate');
        self::assertFalse($blocked->satisfied);
        self::assertSame(IdentityReasonCode::NOT_VERIFIED, $blocked->reasonCode);
    }
}
