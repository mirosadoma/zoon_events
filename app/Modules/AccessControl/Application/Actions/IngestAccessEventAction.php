<?php

namespace App\Modules\AccessControl\Application\Actions;

use App\Exceptions\FoundationException;
use App\Modules\AccessControl\Application\Support\AntiPassbackService;
use App\Modules\AccessControl\Domain\Events\AccessEventIngested;
use App\Modules\AccessControl\Domain\ValueObjects\AcsIntegrationContext;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\AccessEvent;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsLane;
use App\Modules\Audit\Application\AuditedTransaction;
use App\Modules\Credentials\Application\Validation\CredentialValidator;
use App\Modules\Credentials\Infrastructure\Persistence\Models\Credential;
use App\Modules\Shared\Http\Problems\Phase4Problem;
use DateTimeInterface;
use Illuminate\Support\Str;

final readonly class IngestAccessEventAction
{
    public function __construct(
        private CredentialValidator $credentials,
        private AntiPassbackService $antiPassback,
        private AuditedTransaction $audited,
    ) {}

    public function execute(
        AcsIntegrationContext $ctx,
        string $externalEventId,
        string $externalLaneId,
        string $eventType,
        DateTimeInterface $occurredAt,
        ?string $credentialReference = null,
    ): AccessEvent {
        $lane = AcsLane::query()
            ->where('tenant_id', $ctx->tenantId)
            ->where('event_id', $ctx->eventId)
            ->where('external_acs_lane_id', $externalLaneId)
            ->first();

        if ($lane === null) {
            throw Phase4Problem::make('acs_event_out_of_scope');
        }

        $existing = AccessEvent::query()
            ->where('tenant_id', $ctx->tenantId)
            ->where('external_event_id', $externalEventId)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $credentialId = $this->resolveCredentialId($ctx, $credentialReference);

        return $this->audited->run(
            function () use ($ctx, $externalEventId, $lane, $eventType, $occurredAt, $credentialId): AccessEvent {
                $accessEvent = AccessEvent::query()->create([
                    'id' => (string) Str::ulid(),
                    'tenant_id' => $ctx->tenantId,
                    'event_id' => $ctx->eventId,
                    'event_type' => $eventType,
                    'credential_id' => $credentialId,
                    'zone_id' => $lane->zone_id,
                    'lane_id' => $lane->id,
                    'direction' => $eventType,
                    'decision' => 'n/a',
                    'reason_code' => $eventType,
                    'source' => 'acs_gate',
                    'external_event_id' => $externalEventId,
                    'occurred_at' => $occurredAt,
                ]);

                if ($lane->last_seen_at === null || $occurredAt > $lane->last_seen_at) {
                    $lane->forceFill(['last_seen_at' => $occurredAt])->save();
                }

                if ($credentialId !== null) {
                    $this->antiPassback->applyEvent($accessEvent);
                }

                return $accessEvent;
            },
            fn (AccessEvent $event): mixed => event(new AccessEventIngested(
                $ctx->tenantId,
                $ctx->eventId,
                $event->id,
                $lane->id,
                $lane->zone_id,
                $credentialId,
                $eventType,
            )),
        );
    }

    private function resolveCredentialId(AcsIntegrationContext $ctx, ?string $credentialReference): ?string
    {
        if ($credentialReference === null || $credentialReference === '') {
            return null;
        }

        try {
            $validated = $this->credentials->validate(
                $credentialReference,
                $ctx->tenantId,
                $ctx->eventId,
            );
        } catch (FoundationException) {
            return null;
        }

        $credential = Credential::query()
            ->where('tenant_id', $ctx->tenantId)
            ->where('event_id', $ctx->eventId)
            ->where('id', $validated['credential_id'])
            ->first();

        return $credential?->id;
    }
}
