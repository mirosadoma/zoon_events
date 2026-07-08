<?php

namespace Tests\Unit\Scanning;

use App\Models\User;
use App\Modules\Credentials\Application\Actions\RevokeCredential;
use App\Modules\Scanning\Application\Actions\ScanDecisionEvaluatorImpl;
use App\Modules\Scanning\Domain\ValueObjects\ScanContext;
use App\Modules\Scanning\Infrastructure\Persistence\Models\ScanEvent;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\BuildsTenantFixtures;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\CreatesPhase2ScanFixture;
use Tests\Support\Phase2MySqlTestCase;

#[Group('phase-2')]
#[Group('check-in')]
final class ScanDecisionEvaluatorTest extends Phase2MySqlTestCase
{
    use BuildsTenantFixtures;
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use DatabaseTransactions;

    public function test_first_active_credential_scan_is_accepted(): void
    {
        $scan = $this->createIssuedCredentialScanFixture();
        $decision = app(ScanDecisionEvaluatorImpl::class)->evaluate($this->contextFor($scan));

        self::assertSame('accepted', $decision->result);
        self::assertSame('entry_granted', $decision->reasonCode);
    }

    public function test_second_scan_without_override_is_duplicate(): void
    {
        $scan = $this->createIssuedCredentialScanFixture();
        $evaluator = app(ScanDecisionEvaluatorImpl::class);
        $context = $this->contextFor($scan);
        $evaluator->evaluate($context);
        ScanEvent::query()->create([
            'tenant_id' => $scan['fixture']['tenant']->id,
            'event_id' => $scan['fixture']['event']->id,
            'attendee_id' => $scan['credential']->attendee_id,
            'credential_id' => $scan['credential']->id,
            'scanner_type' => 'staff_phone',
            'scanner_id' => $scan['scanner']->id,
            'direction' => 'in',
            'result' => 'accepted',
            'reason' => 'entry_granted',
            'offline_mode' => false,
            'scanned_at' => now(),
        ]);

        $decision = $evaluator->evaluate($context);
        self::assertSame('duplicate', $decision->result);
    }

    public function test_second_scan_with_override_permission_becomes_manual_override(): void
    {
        $scan = $this->createIssuedCredentialScanFixture(['checkin.scan.submit', 'checkin.scan.override']);
        ScanEvent::query()->create([
            'tenant_id' => $scan['fixture']['tenant']->id,
            'event_id' => $scan['fixture']['event']->id,
            'attendee_id' => $scan['credential']->attendee_id,
            'credential_id' => $scan['credential']->id,
            'scanner_type' => 'staff_phone',
            'scanner_id' => $scan['scanner']->id,
            'direction' => 'in',
            'result' => 'accepted',
            'reason' => 'entry_granted',
            'offline_mode' => false,
            'scanned_at' => now(),
        ]);

        $decision = app(ScanDecisionEvaluatorImpl::class)->evaluate(new ScanContext(
            tenantId: $scan['fixture']['tenant']->id,
            eventId: $scan['fixture']['event']->id,
            scannerId: $scan['scanner']->id,
            scannerType: 'staff_phone',
            qrPayload: $scan['token'],
            override: true,
            overrideReason: 'VIP escort',
            actorCanOverride: true,
        ));

        self::assertSame('manual_override', $decision->result);
    }

    public function test_revoked_credential_returns_revoked(): void
    {
        $scan = $this->createIssuedCredentialScanFixture();
        app(RevokeCredential::class)->execute(
            $this->scanContext($scan),
            $scan['fixture']['event']->id,
            $scan['credential']->id,
            'Synthetic revoke',
        );

        $decision = app(ScanDecisionEvaluatorImpl::class)->evaluate($this->contextFor($scan));
        self::assertSame('revoked', $decision->result);
    }

    public function test_expired_credential_returns_expired(): void
    {
        $scan = $this->createIssuedCredentialScanFixture();
        $scan['credential']->refresh();
        $this->travelTo($scan['credential']->expires_at->addMinute());

        $decision = app(ScanDecisionEvaluatorImpl::class)->evaluate($this->contextFor($scan));
        self::assertSame('expired', $decision->result);
    }

    public function test_malformed_payload_returns_rejected(): void
    {
        $scan = $this->createIssuedCredentialScanFixture();
        $context = new ScanContext(
            tenantId: $scan['fixture']['tenant']->id,
            eventId: $scan['fixture']['event']->id,
            scannerId: $scan['scanner']->id,
            scannerType: 'staff_phone',
            qrPayload: 'not-a-valid-token',
        );

        $decision = app(ScanDecisionEvaluatorImpl::class)->evaluate($context);
        self::assertSame('rejected', $decision->result);
        self::assertSame('credential_invalid', $decision->reasonCode);
    }

    public function test_cross_tenant_credential_returns_rejected(): void
    {
        $scan = $this->createIssuedCredentialScanFixture();
        $other = $this->createTenantMember();
        $context = new ScanContext(
            tenantId: $other['tenant']->id,
            eventId: $scan['fixture']['event']->id,
            scannerId: $scan['scanner']->id,
            scannerType: 'staff_phone',
            qrPayload: $scan['token'],
        );

        $decision = app(ScanDecisionEvaluatorImpl::class)->evaluate($context);
        self::assertSame('rejected', $decision->result);
    }

    /** @param array{fixture:array<string,mixed>,token:string,scanner:User} $scan */
    private function contextFor(array $scan): ScanContext
    {
        return new ScanContext(
            tenantId: $scan['fixture']['tenant']->id,
            eventId: $scan['fixture']['event']->id,
            scannerId: $scan['scanner']->id,
            scannerType: 'staff_phone',
            qrPayload: $scan['token'],
        );
    }
}
