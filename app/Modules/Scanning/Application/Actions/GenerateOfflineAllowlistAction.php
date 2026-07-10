<?php

namespace App\Modules\Scanning\Application\Actions;

use App\Modules\Credentials\Infrastructure\Persistence\Models\Credential;
use App\Modules\Shared\Contracts\Clock;

final readonly class GenerateOfflineAllowlistAction
{
    public function __construct(private Clock $clock) {}

    /** @return array{issued_at:\DateTimeInterface,expires_at:\DateTimeInterface,entries_digest:string,entries:list<array{credential_reference_digest:string}>} */
    public function execute(string $tenantId, string $eventId, ?int $windowMinutes = null): array
    {
        $window = $windowMinutes ?? (int) config('wallet.offline_allowlist_default_window_minutes');
        $issuedAt = $this->clock->now();
        $expiresAt = $issuedAt->modify("+{$window} minutes");

        $digests = Credential::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('status', 'active')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn (string $credentialId): string => hash('sha256', $credentialId))
            ->values()
            ->all();

        sort($digests);

        return [
            'issued_at' => $issuedAt,
            'expires_at' => $expiresAt,
            'entries_digest' => hash('sha256', implode('', $digests)),
            'entries' => array_map(
                fn (string $digest): array => ['credential_reference_digest' => $digest],
                $digests,
            ),
        ];
    }
}
