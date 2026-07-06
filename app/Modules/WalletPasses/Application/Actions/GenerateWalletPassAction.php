<?php

namespace App\Modules\WalletPasses\Application\Actions;

use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\Audit\Application\AuditWriter;
use App\Modules\Credentials\Application\Presentation\CredentialPresentationToken;
use App\Modules\Credentials\Infrastructure\Persistence\Models\Credential;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Shared\Application\DataProtection\PersonalDataCipher;
use App\Modules\Shared\Http\Problems\Phase2Problem;
use App\Modules\Ticketing\Infrastructure\Persistence\Models\TicketType;
use App\Modules\WalletPasses\Contracts\WalletAdapter;
use App\Modules\WalletPasses\Domain\Events\WalletPassGenerated;
use App\Modules\WalletPasses\Domain\Events\WalletPassGenerationDenied;
use App\Modules\WalletPasses\Domain\ValueObjects\WalletPassGenerationRequest;
use App\Modules\WalletPasses\Domain\WalletPassStatus;
use App\Modules\WalletPasses\Infrastructure\Adapters\Apple\AppleWalletAdapter;
use App\Modules\WalletPasses\Infrastructure\Adapters\Google\GoogleWalletAdapter;
use App\Modules\WalletPasses\Infrastructure\Persistence\Models\WalletPass;
use App\Modules\WalletPasses\Testing\FakeWalletAdapter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class GenerateWalletPassAction
{
    public function __construct(
        private CredentialPresentationToken $presentationTokens,
        private PersonalDataCipher $cipher,
        private AuditWriter $audit,
    ) {}

    public function execute(string $tenantId, string $eventId, string $attendeeId, string $credentialId, string $provider, string $locale = 'en'): WalletPass
    {
        $credential = Credential::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('attendee_id', $attendeeId)
            ->findOrFail($credentialId);

        if ($credential->status !== 'active') {
            event(new WalletPassGenerationDenied($tenantId, $eventId, $credentialId, $provider));
            $this->audit->write(
                'tenant',
                $tenantId,
                'wallet_pass.generation_denied',
                'rejected',
                reasonCode: 'credential_not_active',
                targetType: 'credential',
                targetId: $credentialId,
                metadata: ['event_id' => $eventId, 'provider' => $provider],
            );
            throw Phase2Problem::make('credential_not_active');
        }

        $existing = WalletPass::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('attendee_id', $attendeeId)
            ->where('credential_id', $credentialId)
            ->whereNull('superseded_by_id')
            ->where('provider', $provider)
            ->whereIn('status', [WalletPassStatus::Active, WalletPassStatus::Updated, WalletPassStatus::Created])
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $attendee = Attendee::query()->where('tenant_id', $tenantId)->where('event_id', $eventId)->findOrFail($attendeeId);
        $event = Event::query()->where('tenant_id', $tenantId)->findOrFail($eventId);
        $ticket = TicketType::query()->where('tenant_id', $tenantId)->where('event_id', $eventId)->findOrFail($credential->ticket_type_id);
        $attendeeName = trim($this->cipher->decrypt(
            ['key_id' => $attendee->encryption_key_id, 'ciphertext' => $attendee->first_name_ciphertext],
            "{$tenantId}:{$eventId}:attendee",
        ).' '.$this->cipher->decrypt(
            ['key_id' => $attendee->encryption_key_id, 'ciphertext' => $attendee->last_name_ciphertext],
            "{$tenantId}:{$eventId}:attendee",
        ));

        $serial = (string) Str::ulid();
        $adapter = $this->resolveAdapter($provider);
        $result = $adapter->generate(new WalletPassGenerationRequest(
            tenantId: $tenantId,
            eventId: $eventId,
            attendeeId: $attendeeId,
            credentialId: $credentialId,
            credentialStatus: $credential->status,
            provider: $provider,
            passSerialNumber: $serial,
            locale: $locale,
            credentialToken: $this->presentationTokens->resolve($credential),
            eventName: $event->name_en,
            eventDate: $event->start_at?->toIso8601String() ?? now()->toIso8601String(),
            eventLocation: $event->location_name_en ?? '',
            attendeeName: $attendeeName,
            ticketTypeLabel: $ticket->name_en,
        ));

        if (in_array($result->status, ['failed', 'unavailable'], true)) {
            return $this->recordDegradedPass(
                $tenantId,
                $eventId,
                $attendeeId,
                $credentialId,
                $provider,
                $serial,
                $result->reasonCode ?? 'wallet_provider_unavailable',
            );
        }

        return DB::transaction(function () use ($tenantId, $eventId, $attendeeId, $credentialId, $provider, $serial, $result): WalletPass {
            // Re-check under lock to prevent duplicate active passes from concurrent requests.
            $concurrent = WalletPass::query()
                ->where('tenant_id', $tenantId)
                ->where('event_id', $eventId)
                ->where('attendee_id', $attendeeId)
                ->where('credential_id', $credentialId)
                ->whereNull('superseded_by_id')
                ->where('provider', $provider)
                ->whereIn('status', [WalletPassStatus::Active, WalletPassStatus::Updated, WalletPassStatus::Created])
                ->lockForUpdate()
                ->first();

            if ($concurrent !== null) {
                return $concurrent;
            }

            $pass = WalletPass::query()->create([
                'tenant_id' => $tenantId,
                'event_id' => $eventId,
                'attendee_id' => $attendeeId,
                'credential_id' => $credentialId,
                'provider' => $provider,
                'pass_serial_number' => $serial,
                'pass_url' => $result->passUrl,
                'status' => WalletPassStatus::Active,
                'last_pushed_at' => now(),
                'apple_authentication_token' => $provider === 'apple' ? $result->authenticationToken : null,
                'pass_content_updated_at' => now(),
            ]);

            event(new WalletPassGenerated($tenantId, $eventId, $pass->id, $provider));
            $this->audit->write(
                'tenant',
                $tenantId,
                'wallet_pass.generated',
                'succeeded',
                targetType: 'wallet_pass',
                targetId: $pass->id,
                metadata: ['event_id' => $eventId, 'provider' => $provider, 'credential_id' => $credentialId],
            );

            return $pass;
        });
    }

    private function recordDegradedPass(
        string $tenantId,
        string $eventId,
        string $attendeeId,
        string $credentialId,
        string $provider,
        string $serial,
        string $reasonCode,
    ): WalletPass {
        return DB::transaction(function () use ($tenantId, $eventId, $attendeeId, $credentialId, $provider, $serial, $reasonCode): WalletPass {
            $pass = WalletPass::query()->create([
                'tenant_id' => $tenantId,
                'event_id' => $eventId,
                'attendee_id' => $attendeeId,
                'credential_id' => $credentialId,
                'provider' => $provider,
                'pass_serial_number' => $serial,
                'pass_url' => null,
                'status' => WalletPassStatus::Failed,
                'last_pushed_at' => now(),
                'last_push_reason_code' => $reasonCode,
                'pass_content_updated_at' => now(),
            ]);

            event(new WalletPassGenerationDenied($tenantId, $eventId, $credentialId, $provider));
            $this->audit->write(
                'tenant',
                $tenantId,
                'wallet_pass.generation_denied',
                'failed',
                reasonCode: $reasonCode,
                targetType: 'wallet_pass',
                targetId: $pass->id,
                metadata: ['event_id' => $eventId, 'provider' => $provider, 'credential_id' => $credentialId],
            );

            return $pass;
        });
    }

    private function resolveAdapter(string $provider): WalletAdapter
    {
        $binding = $provider === 'apple'
            ? config('wallet.default_apple_adapter')
            : config('wallet.default_google_adapter');

        if ($binding === 'fake') {
            return app(FakeWalletAdapter::class);
        }

        return $provider === 'apple'
            ? app(AppleWalletAdapter::class)
            : app(GoogleWalletAdapter::class);
    }
}
