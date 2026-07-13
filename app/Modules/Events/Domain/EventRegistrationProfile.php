<?php

namespace App\Modules\Events\Domain;

use App\Modules\Events\Infrastructure\Persistence\Models\Event;

final class EventRegistrationProfile
{
    public const SYSTEM_REGISTRATION_TICKET_CODE = '__registration__';

    public static function requiresTicketConfiguration(Event|string $tier, ?string $registrationMode = null): bool
    {
        if ($tier instanceof Event) {
            return self::requiresTicketConfiguration($tier->tier, $tier->registration_mode);
        }

        return $tier === EventTier::Public->value
            && $registrationMode === RegistrationMode::PaidTicketing->value;
    }

    public static function requiresPriceTiers(Event|string $tier, ?string $registrationMode = null): bool
    {
        return self::requiresTicketConfiguration($tier, $registrationMode);
    }

    public static function allowsPaidTicketing(string $tier): bool
    {
        return $tier === EventTier::Public->value;
    }

    public static function normalizeRegistrationMode(string $tier, string $registrationMode): string
    {
        if (! self::allowsPaidTicketing($tier)) {
            return RegistrationMode::FreeRegistration->value;
        }

        return $registrationMode;
    }

    /** @return array{requires_ticketing:bool,requires_price_tiers:bool,allows_paid_ticketing:bool,uses_system_registration_slot:bool} */
    public static function capabilities(Event $event): array
    {
        $requiresTicketing = self::requiresTicketConfiguration($event);

        return [
            'requires_ticketing' => $requiresTicketing,
            'requires_price_tiers' => $requiresTicketing,
            'allows_paid_ticketing' => self::allowsPaidTicketing($event->tier),
            'uses_system_registration_slot' => ! $requiresTicketing,
        ];
    }
}
