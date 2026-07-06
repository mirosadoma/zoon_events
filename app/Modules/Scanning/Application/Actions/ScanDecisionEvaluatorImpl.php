<?php

namespace App\Modules\Scanning\Application\Actions;

use App\Exceptions\FoundationException;
use App\Modules\Credentials\Application\Validation\CredentialValidator;
use App\Modules\Credentials\Infrastructure\Persistence\Models\Credential;
use App\Modules\Scanning\Contracts\ScanDecisionEvaluator;
use App\Modules\Scanning\Domain\Results\ScanDecision;
use App\Modules\Scanning\Domain\SingleEntryEvaluator;
use App\Modules\Scanning\Domain\ValueObjects\ScanContext;

final readonly class ScanDecisionEvaluatorImpl implements ScanDecisionEvaluator
{
    public function __construct(
        private CredentialValidator $credentials,
        private SingleEntryEvaluator $singleEntry,
    ) {}

    public function evaluate(ScanContext $context): ScanDecision
    {
        try {
            $validated = $this->credentials->validate(
                $context->qrPayload,
                $context->tenantId,
                $context->eventId,
            );
        } catch (FoundationException $exception) {
            return match ($exception->problemCode) {
                'credential_expired' => new ScanDecision('expired', 'credential_expired'),
                'credential_revoked' => new ScanDecision('revoked', 'credential_revoked'),
                default => new ScanDecision('rejected', 'credential_invalid'),
            };
        }

        $credential = Credential::query()
            ->where('tenant_id', $context->tenantId)
            ->where('event_id', $context->eventId)
            ->findOrFail($validated['credential_id']);

        if ($this->singleEntry->isDuplicate(
            $context->tenantId,
            $context->eventId,
            $credential->id,
            $credential->ticket_type_id,
        )) {
            if ($context->override
                && $context->overrideReason !== null
                && $context->overrideReason !== ''
                && $context->actorCanOverride) {
                return new ScanDecision(
                    'manual_override',
                    'duplicate_overridden',
                    $credential->id,
                    $credential->attendee_id,
                );
            }

            return new ScanDecision('duplicate', 'already_checked_in', $credential->id, $credential->attendee_id);
        }

        return new ScanDecision('accepted', 'entry_granted', $credential->id, $credential->attendee_id);
    }
}
