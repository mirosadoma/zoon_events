<?php

namespace App\Modules\VenueMarketplace\Application\Audit;

use InvalidArgumentException;

final readonly class MarketplaceAuditEvent
{
    private const FORBIDDEN_KEY_FRAGMENTS = [
        'secret', 'credential', 'password', 'token', 'binding', 'external_reference',
        'request_body', 'decision_reason', 'dispute_reason', 'note',
    ];

    public function __construct(
        public string $action,
        public string $scope,
        public string $outcome,
        public string $correlationId,
        public string $targetPublicId,
        public array $payload = [],
        public ?int $ownerTenantId = null,
        public ?int $organizerTenantId = null,
        public ?int $actorUserId = null,
        public ?string $reasonCode = null,
    ) {
        if ($action === '' || $correlationId === '' || $targetPublicId === ''
            || ! in_array($scope, ['owner', 'organizer', 'platform'], true)
            || ! in_array($outcome, ['succeeded', 'denied', 'failed'], true)) {
            throw new InvalidArgumentException('Invalid marketplace audit event.');
        }

        self::assertSanitized($payload);
    }

    private static function assertSanitized(array $payload): void
    {
        foreach ($payload as $key => $value) {
            $normalized = strtolower((string) $key);
            foreach (self::FORBIDDEN_KEY_FRAGMENTS as $fragment) {
                if (str_contains($normalized, $fragment)) {
                    throw new InvalidArgumentException('Marketplace audit payload contains forbidden data.');
                }
            }
            if (is_array($value)) {
                self::assertSanitized($value);
            }
        }
    }
}
