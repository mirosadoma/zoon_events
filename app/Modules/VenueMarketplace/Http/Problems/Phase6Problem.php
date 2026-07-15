<?php

namespace App\Modules\VenueMarketplace\Http\Problems;

final class Phase6Problem
{
    public const ORGANIZATION_TYPE_NOT_ELIGIBLE = 'organization_type_not_eligible';

    public const MARKETPLACE_PERMISSION_DENIED = 'marketplace_permission_denied';

    public const MARKETPLACE_VENUE_NOT_FOUND = 'marketplace_venue_not_found';

    public const MARKETPLACE_VENUE_NOT_PUBLISHABLE = 'marketplace_venue_not_publishable';

    public const MARKETPLACE_ASSET_NOT_FOUND = 'marketplace_asset_not_found';

    public const MARKETPLACE_ASSET_NOT_PUBLISHABLE = 'marketplace_asset_not_publishable';

    public const MARKETPLACE_ASSET_UNAVAILABLE = 'marketplace_asset_unavailable';

    public const MARKETPLACE_AVAILABILITY_CONFLICT = 'marketplace_availability_conflict';

    public const MARKETPLACE_CATALOG_UNAVAILABLE = 'marketplace_catalog_unavailable';

    public const MARKETPLACE_ADAPTER_UNAVAILABLE = 'marketplace_adapter_unavailable';

    public const MARKETPLACE_EVENT_NOT_FOUND = 'marketplace_event_not_found';

    public const MARKETPLACE_EVENT_INELIGIBLE_STATUS = 'marketplace_event_ineligible_status';

    public const MARKETPLACE_WINDOW_INVALID = 'marketplace_window_invalid';

    public const MARKETPLACE_MIXED_VENUE = 'marketplace_mixed_venue';

    public const MARKETPLACE_MIXED_CURRENCY = 'marketplace_mixed_currency';

    public const MARKETPLACE_QUOTE_CHANGED = 'marketplace_quote_changed';

    public const MARKETPLACE_RENTAL_NOT_FOUND = 'marketplace_rental_not_found';

    public const MARKETPLACE_RENTAL_STATE_CONFLICT = 'marketplace_rental_state_conflict';

    public const MARKETPLACE_RESERVATION_CONFLICT = 'marketplace_reservation_conflict';

    public const MARKETPLACE_DELEGATION_NOT_FOUND = 'marketplace_delegation_not_found';

    public const MARKETPLACE_DELEGATION_NOT_STARTED = 'marketplace_delegation_not_started';

    public const MARKETPLACE_DELEGATION_EXPIRED = 'marketplace_delegation_expired';

    public const MARKETPLACE_DELEGATION_REVOKED = 'marketplace_delegation_revoked';

    public const MARKETPLACE_DELEGATION_DEGRADED = 'marketplace_delegation_degraded';

    public const MARKETPLACE_EVENT_SCOPE_DENIED = 'marketplace_event_scope_denied';

    public const MARKETPLACE_ASSET_SCOPE_DENIED = 'marketplace_asset_scope_denied';

    public const MARKETPLACE_CAPABILITY_DENIED = 'marketplace_capability_denied';

    public const MARKETPLACE_STATEMENT_NOT_FOUND = 'marketplace_statement_not_found';

    public const MARKETPLACE_STATEMENT_NOT_READY = 'marketplace_statement_not_ready';

    public const MARKETPLACE_DISPUTE_NOT_FOUND = 'marketplace_dispute_not_found';

    public const MARKETPLACE_DISPUTE_STATE_CONFLICT = 'marketplace_dispute_state_conflict';

    /** @return list<string> */
    public static function reasonCodes(): array
    {
        return array_values((new \ReflectionClass(self::class))->getConstants());
    }

    public static function statusFor(string $reasonCode): int
    {
        return match ($reasonCode) {
            self::MARKETPLACE_VENUE_NOT_FOUND,
            self::MARKETPLACE_ASSET_NOT_FOUND,
            self::MARKETPLACE_EVENT_NOT_FOUND,
            self::MARKETPLACE_RENTAL_NOT_FOUND,
            self::MARKETPLACE_DELEGATION_NOT_FOUND,
            self::MARKETPLACE_STATEMENT_NOT_FOUND,
            self::MARKETPLACE_DISPUTE_NOT_FOUND => 404,
            self::MARKETPLACE_QUOTE_CHANGED,
            self::MARKETPLACE_RENTAL_STATE_CONFLICT,
            self::MARKETPLACE_RESERVATION_CONFLICT,
            self::MARKETPLACE_STATEMENT_NOT_READY,
            self::MARKETPLACE_DISPUTE_STATE_CONFLICT => 409,
            self::MARKETPLACE_CATALOG_UNAVAILABLE,
            self::MARKETPLACE_ADAPTER_UNAVAILABLE => 503,
            self::MARKETPLACE_VENUE_NOT_PUBLISHABLE,
            self::MARKETPLACE_ASSET_NOT_PUBLISHABLE,
            self::MARKETPLACE_ASSET_UNAVAILABLE,
            self::MARKETPLACE_AVAILABILITY_CONFLICT,
            self::MARKETPLACE_EVENT_INELIGIBLE_STATUS,
            self::MARKETPLACE_WINDOW_INVALID,
            self::MARKETPLACE_MIXED_VENUE,
            self::MARKETPLACE_MIXED_CURRENCY => 422,
            default => 403,
        };
    }

    public static function detailFor(string $reasonCode, string $locale = 'en'): string
    {
        if (! in_array($reasonCode, self::reasonCodes(), true)) {
            return 'The marketplace request could not be completed.';
        }

        return match ($locale) {
            'ar' => 'تعذر إكمال طلب السوق.',
            default => 'The marketplace request could not be completed.',
        };
    }
}
