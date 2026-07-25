<?php

namespace App\Modules\Events\Domain;

use App\Modules\Events\Infrastructure\Persistence\Models\EventEmailTemplate;

final class EventEmailTemplateTypes
{
    /** @var list<string> */
    public const REQUIRED = ['invitation', 'otp', 'confirmation'];

    public static function requiredCount(): int
    {
        return count(self::REQUIRED);
    }

    public static function configuredCount(string $tenantId, int|string $eventId): int
    {
        return (int) EventEmailTemplate::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->whereIn('type', self::REQUIRED)
            ->distinct()
            ->count('type');
    }

    public static function isComplete(string $tenantId, int|string $eventId): bool
    {
        return self::configuredCount($tenantId, $eventId) >= self::requiredCount();
    }
}
