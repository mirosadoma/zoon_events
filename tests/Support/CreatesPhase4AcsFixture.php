<?php

namespace Tests\Support;

use App\Modules\AccessControl\Application\Actions\RegisterAcsIntegrationCredentialAction;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsAuthorizationRule;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsIntegrationCredential;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsLane;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsZone;
use App\Modules\Credentials\Infrastructure\Persistence\Models\Credential;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use DateTimeInterface;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

trait CreatesPhase4AcsFixture
{
    use BuildsTenantFixtures;

    /**
     * @param  array{fixture:array<string,mixed>,credential:Credential,token:string}  $scanFixture
     * @return array{
     *     zone:AcsZone,
     *     lane:AcsLane,
     *     rule:AcsAuthorizationRule,
     *     secret:string,
     *     credential:Credential,
     *     token:string,
     *     event:Event,
     * }
     */
    protected function createAcsAuthorizationFixture(array $scanFixture, bool $admissionLane = false): array
    {
        $event = $scanFixture['fixture']['event'];
        $credential = $scanFixture['credential'];

        $zone = AcsZone::factory()->create([
            'tenant_id' => $event->tenant_id,
            'event_id' => $event->id,
        ]);

        $lane = AcsLane::factory()->create([
            'tenant_id' => $event->tenant_id,
            'event_id' => $event->id,
            'zone_id' => $zone->id,
            'is_admission_lane' => $admissionLane,
        ]);

        $rule = AcsAuthorizationRule::factory()->create([
            'tenant_id' => $event->tenant_id,
            'event_id' => $event->id,
            'zone_id' => $zone->id,
            'ticket_type_id' => $credential->ticket_type_id,
        ]);

        $registered = app(RegisterAcsIntegrationCredentialAction::class)->execute(
            $event->tenant_id,
            $event->id,
            'ACS Integration',
            ['authorize', 'event.ingest', 'emergency.ingest'],
        );

        return [
            'zone' => $zone,
            'lane' => $lane,
            'rule' => $rule,
            'secret' => $registered['secret'],
            'credential' => $credential,
            'token' => $scanFixture['token'],
            'event' => $event,
        ];
    }

    /** @return array<string, string> */
    protected function acsIntegrationHeaders(string $secret, string $idempotencyKey = 'acs-auth-test'): array
    {
        return [
            'Authorization' => 'AcsIntegration '.$secret,
            'Idempotency-Key' => $idempotencyKey,
        ];
    }

    protected function acsIntegrationWithoutAuthorizeCapability(Event $event): string
    {
        AcsIntegrationCredential::query()
            ->where('tenant_id', $event->tenant_id)
            ->where('event_id', $event->id)
            ->delete();

        $registered = app(RegisterAcsIntegrationCredentialAction::class)->execute(
            $event->tenant_id,
            $event->id,
            'Events only',
            ['event.ingest'],
        );

        return $registered['secret'];
    }

    /**
     * @param  array{fixture:array<string,mixed>}  $scanFixture
     * @return array<string, string>
     */
    protected function acsTenantHeaders(array $scanFixture, string $idempotencyKey): array
    {
        return array_merge($this->tenantHeaders($scanFixture['fixture']['tenant']), [
            'Idempotency-Key' => $idempotencyKey,
        ]);
    }

    /** @param array{fixture:array<string,mixed>,event:Event,secret:string,lane:AcsLane,token?:string,credential?:Credential} $acs */
    protected function postAccessEventCallback(
        array $acs,
        string $externalEventId,
        string $eventType,
        ?string $credentialReference = null,
        ?string $idempotencyKey = null,
        ?DateTimeInterface $occurredAt = null,
    ): TestResponse {
        $payload = [
            'external_event_id' => $externalEventId,
            'external_acs_lane_id' => $acs['lane']->external_acs_lane_id,
            'event_type' => $eventType,
            'occurred_at' => ($occurredAt ?? now())->format('Y-m-d\TH:i:s.uP'),
        ];

        if ($credentialReference !== null) {
            $payload['credential_reference'] = $credentialReference;
        }

        return $this->postJson(
            '/api/v1/acs/v1/events',
            $payload,
            $this->acsIntegrationHeaders($acs['secret'], $idempotencyKey ?? 'acs-event-'.Str::ulid()),
        );
    }
}
