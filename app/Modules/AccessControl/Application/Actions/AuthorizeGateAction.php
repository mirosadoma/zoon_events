<?php

namespace App\Modules\AccessControl\Application\Actions;

use App\Exceptions\FoundationException;
use App\Modules\AccessControl\Application\Support\AcsRuleEvaluator;
use App\Modules\AccessControl\Application\Support\AntiPassbackService;
use App\Modules\AccessControl\Application\Support\EmergencyStateService;
use App\Modules\AccessControl\Contracts\AcsAdapter;
use App\Modules\AccessControl\Domain\Events\GateAuthorized;
use App\Modules\AccessControl\Domain\Events\GateDenied;
use App\Modules\AccessControl\Domain\ValueObjects\AcsDecisionResult;
use App\Modules\AccessControl\Domain\ValueObjects\AcsIntegrationContext;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\AccessEvent;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsLane;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsZone;
use App\Modules\Audit\Application\AuditedTransaction;
use App\Modules\Credentials\Application\Validation\CredentialValidator;
use App\Modules\Credentials\Infrastructure\Persistence\Models\Credential;
use App\Modules\Scanning\Application\Actions\SubmitScanAction;
use App\Modules\Scanning\Domain\ValueObjects\ScanContext;
use App\Modules\Shared\Http\Problems\Phase4Problem;
use App\Modules\Ticketing\Infrastructure\Persistence\Models\TicketType;

final readonly class AuthorizeGateAction
{
    public function __construct(
        private CredentialValidator $credentials,
        private AcsRuleEvaluator $rules,
        private AntiPassbackService $antiPassback,
        private EmergencyStateService $emergencyState,
        private AcsAdapter $adapter,
        private SubmitScanAction $submitScan,
        private AuditedTransaction $audited,
    ) {}

    public function execute(
        AcsIntegrationContext $ctx,
        string $externalLaneId,
        ?string $credentialReference,
        string $direction,
    ): AcsDecisionResult {
        $lane = AcsLane::query()
            ->where('tenant_id', $ctx->tenantId)
            ->where('event_id', $ctx->eventId)
            ->where('external_acs_lane_id', $externalLaneId)
            ->where('status', 'active')
            ->first();

        if ($lane === null) {
            throw Phase4Problem::make('acs_lane_unmapped');
        }

        $zone = AcsZone::query()
            ->where('tenant_id', $ctx->tenantId)
            ->where('event_id', $ctx->eventId)
            ->where('id', $lane->zone_id)
            ->first();

        if ($zone === null) {
            throw Phase4Problem::make('acs_lane_unmapped');
        }

        if ($this->emergencyState->isActiveForZone($ctx->tenantId, $ctx->eventId, $zone->id)
            && $zone->emergency_egress_mode === 'fail_open') {
            return $this->recordDecision(
                $ctx,
                $lane,
                $zone,
                $direction,
                null,
                'allow',
                'emergency_fail_open',
            );
        }

        if ($credentialReference === null || $credentialReference === '') {
            return $this->recordDecision(
                $ctx,
                $lane,
                $zone,
                $direction,
                null,
                'deny',
                'credential_unknown',
            );
        }

        try {
            $validated = $this->credentials->validate(
                $credentialReference,
                $ctx->tenantId,
                $ctx->eventId,
            );
        } catch (FoundationException $exception) {
            $reasonCode = match ($exception->problemCode) {
                'credential_expired' => 'credential_expired',
                'credential_revoked' => 'credential_revoked',
                default => 'credential_unknown',
            };

            return $this->recordDecision(
                $ctx,
                $lane,
                $zone,
                $direction,
                null,
                'deny',
                $reasonCode,
            );
        }

        $credential = Credential::query()
            ->where('tenant_id', $ctx->tenantId)
            ->where('event_id', $ctx->eventId)
            ->where('id', $validated['credential_id'])
            ->first();

        if ($credential === null) {
            return $this->recordDecision(
                $ctx,
                $lane,
                $zone,
                $direction,
                null,
                'deny',
                'credential_unknown',
            );
        }

        $attendeeType = TicketType::query()
            ->where('tenant_id', $ctx->tenantId)
            ->where('event_id', $ctx->eventId)
            ->where('id', $credential->ticket_type_id)
            ->value('attendee_type');

        $ruleFailure = $this->rules->evaluate(
            $ctx->tenantId,
            $ctx->eventId,
            $credential->ticket_type_id,
            $attendeeType,
            $zone->id,
            $lane->id,
            $direction,
            now(),
        );

        if ($ruleFailure !== null) {
            return $this->recordDecision(
                $ctx,
                $lane,
                $zone,
                $direction,
                $credential->id,
                'deny',
                $ruleFailure,
            );
        }

        if ($direction === 'entry'
            && $zone->anti_passback_enabled
            && ! $this->rules->isAntiPassbackExempt(
                $ctx->tenantId,
                $ctx->eventId,
                $credential->ticket_type_id,
                $attendeeType,
                $zone->id,
                $lane->id,
                $direction,
                now(),
            )
            && $this->antiPassback->isInside($ctx->tenantId, $ctx->eventId, $credential->id, $zone->id)) {
            return $this->recordDecision(
                $ctx,
                $lane,
                $zone,
                $direction,
                $credential->id,
                'deny',
                'anti_passback_violation',
            );
        }

        if (! $this->adapter->isAvailable()) {
            if ($zone->unavailability_mode === 'fail_open') {
                return $this->recordDecision(
                    $ctx,
                    $lane,
                    $zone,
                    $direction,
                    $credential->id,
                    'allow',
                    'acs_unavailable_fail_open',
                    $direction === 'entry' && $lane->is_admission_lane,
                    $credentialReference,
                );
            }

            return $this->recordDecision(
                $ctx,
                $lane,
                $zone,
                $direction,
                $credential->id,
                'deny',
                'acs_unavailable_fail_closed',
            );
        }

        return $this->recordDecision(
            $ctx,
            $lane,
            $zone,
            $direction,
            $credential->id,
            'allow',
            'allowed',
            $direction === 'entry' && $lane->is_admission_lane,
            $credentialReference,
        );
    }

    private function recordDecision(
        AcsIntegrationContext $ctx,
        AcsLane $lane,
        AcsZone $zone,
        string $direction,
        ?string $credentialId,
        string $decision,
        string $reasonCode,
        bool $recordAdmissionScan = false,
        ?string $credentialReference = null,
    ): AcsDecisionResult {
        return $this->audited->run(
            function () use ($ctx, $lane, $zone, $direction, $credentialId, $decision, $reasonCode, $recordAdmissionScan, $credentialReference): AcsDecisionResult {
                $scanEventId = null;

                if ($recordAdmissionScan && $decision === 'allow' && $credentialId !== null) {
                    $submission = $this->submitScan->execute(new ScanContext(
                        tenantId: $ctx->tenantId,
                        eventId: $ctx->eventId,
                        scannerId: $lane->id,
                        scannerType: 'acs_gate',
                        qrPayload: is_string($credentialReference) && $credentialReference !== '' ? $credentialReference : '',
                        credentialId: is_string($credentialReference) && $credentialReference !== '' ? null : $credentialId,
                    ));
                    $scanEventId = $submission->scanEventId;
                }

                $accessEvent = AccessEvent::query()->create([
                    'tenant_id' => $ctx->tenantId,
                    'event_id' => $ctx->eventId,
                    'event_type' => 'decision',
                    'credential_id' => $credentialId,
                    'zone_id' => $zone->id,
                    'lane_id' => $lane->id,
                    'direction' => $direction,
                    'decision' => $decision,
                    'reason_code' => $reasonCode,
                    'source' => 'acs_gate',
                    'scan_event_id' => $scanEventId,
                    'occurred_at' => now(),
                ]);

                return new AcsDecisionResult(
                    $decision,
                    $reasonCode,
                    $accessEvent->id,
                    $scanEventId,
                );
            },
            function (AcsDecisionResult $result) use ($ctx, $lane, $zone, $direction, $credentialId, $decision, $reasonCode): void {
                $event = $decision === 'allow'
                    ? new GateAuthorized(
                        $ctx->tenantId,
                        $ctx->eventId,
                        $result->accessEventId,
                        $credentialId,
                        $zone->id,
                        $lane->id,
                        $direction,
                        $reasonCode,
                    )
                    : new GateDenied(
                        $ctx->tenantId,
                        $ctx->eventId,
                        $result->accessEventId,
                        $credentialId,
                        $zone->id,
                        $lane->id,
                        $direction,
                        $reasonCode,
                    );

                event($event);
            },
        );
    }
}
