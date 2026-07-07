<?php

namespace App\Modules\AccessControl\Application\Actions;

use App\Modules\AccessControl\Domain\Events\AcsIntegrationCredentialRegistered;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsIntegrationCredential;
use App\Modules\Audit\Application\AuditedTransaction;
use App\Modules\Shared\Http\Problems\Phase4Problem;
use Illuminate\Support\Str;

final readonly class RegisterAcsIntegrationCredentialAction
{
    private const ALLOWED_CAPABILITIES = ['authorize', 'event.ingest', 'emergency.ingest'];

    public function __construct(private AuditedTransaction $audited) {}

    /**
     * @param  list<string>  $capabilities
     * @return array{id: string, secret: string, name: string, capabilities: list<string>, expiresAt: \DateTimeInterface}
     */
    public function execute(
        string $tenantId,
        string $eventId,
        string $name,
        array $capabilities,
    ): array {
        foreach ($capabilities as $capability) {
            if (! in_array($capability, self::ALLOWED_CAPABILITIES, true)) {
                throw Phase4Problem::make('acs_capability_denied');
            }
        }

        $secretLength = (int) config('acs.integration.secret_length', 40);
        $ttlHours = (int) config('acs.integration.credential_ttl_hours', 168);

        $rawSecret = sodium_bin2base64(
            random_bytes($secretLength),
            SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING,
        );

        $expiresAt = now()->addHours($ttlHours);

        return $this->audited->run(
            function () use ($tenantId, $eventId, $name, $capabilities, $rawSecret, $expiresAt): array {
                AcsIntegrationCredential::query()
                    ->where('tenant_id', $tenantId)
                    ->where('event_id', $eventId)
                    ->where('status', 'active')
                    ->whereNull('revoked_at')
                    ->get()
                    ->each(fn (AcsIntegrationCredential $credential) => $credential->forceFill([
                        'status' => 'revoked',
                        'revoked_at' => now(),
                    ])->save());

                $credential = AcsIntegrationCredential::create([
                    'id' => (string) Str::ulid(),
                    'tenant_id' => $tenantId,
                    'event_id' => $eventId,
                    'name' => $name,
                    'secret_hash' => hash('sha256', $rawSecret),
                    'capabilities' => array_values($capabilities),
                    'status' => 'active',
                    'expires_at' => $expiresAt,
                ]);

                return [
                    'id' => $credential->id,
                    'secret' => $rawSecret,
                    'name' => $name,
                    'capabilities' => array_values($capabilities),
                    'expiresAt' => $expiresAt->toDateTimeImmutable(),
                    'credentialId' => $credential->id,
                ];
            },
            fn (array $result): mixed => event(new AcsIntegrationCredentialRegistered(
                $tenantId,
                $eventId,
                $result['credentialId'],
            )),
        );
    }
}
