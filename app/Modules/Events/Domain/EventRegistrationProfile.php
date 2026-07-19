<?php

namespace App\Modules\Events\Domain;

use App\Modules\Events\Infrastructure\Persistence\Models\Event;

final class EventRegistrationProfile
{
    public const SYSTEM_REGISTRATION_TICKET_CODE = '__registration__';

    public static function requiresTicketConfiguration(Event|string $tier, ?string $registrationMode = null): bool
    {
        return false;
    }

    public static function requiresPriceTiers(Event|string $tier, ?string $registrationMode = null): bool
    {
        return false;
    }

    public static function allowsPaidTicketing(string $tier): bool
    {
        return false;
    }

    public static function normalizeRegistrationMode(string $tier, string $registrationMode): string
    {
        return RegistrationMode::FreeRegistration->value;
    }

    /** @return array{requires_ticketing:bool,requires_price_tiers:bool,allows_paid_ticketing:bool,uses_system_registration_slot:bool} */
    public static function capabilities(Event $event): array
    {
        return [
            'requires_ticketing' => false,
            'requires_price_tiers' => false,
            'allows_paid_ticketing' => false,
            'uses_system_registration_slot' => true,
        ];
    }
}
