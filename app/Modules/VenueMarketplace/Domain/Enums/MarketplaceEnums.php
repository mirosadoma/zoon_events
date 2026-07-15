<?php

namespace App\Modules\VenueMarketplace\Domain\Enums;

final class MarketplaceEnums
{
    public const ORGANIZATION_TYPES = ['organizer', 'venue_owner', 'hybrid'];

    public const ASSET_TYPES = ['turnstile', 'security_gate', 'camera', 'kiosk', 'printer', 'scanner', 'access_lane', 'access_zone'];

    public const VENUE_STATUSES = ['draft', 'active', 'suspended', 'archived'];

    public const ASSET_STATUSES = ['draft', 'active', 'maintenance', 'offline', 'retired'];

    public const PRICING_MODELS = ['per_hour', 'per_day', 'per_rental'];

    public const PUBLICATION_STATUSES = ['active', 'withdrawn'];

    public const RENTAL_STATUSES = ['requested', 'approved', 'rejected', 'active', 'completed', 'cancelled', 'revoked'];

    public const RESERVATION_STATUSES = ['reserved', 'active', 'completed', 'released'];

    public const DELEGATION_STATUSES = ['pending', 'active', 'degraded', 'revoked', 'expired', 'completed'];

    public const PROVISIONING_STATUSES = ['pending', 'provisioned', 'degraded', 'released', 'not_applicable'];

    public const STATEMENT_STATUSES = ['issued', 'superseded'];

    public const RENTAL_DISPUTE_STATUSES = ['none', 'open', 'under_review', 'resolved'];

    public const DISPUTE_STATUSES = ['open', 'under_review', 'resolved', 'rejected'];

    public const CONTROL_FAMILIES = ['acs', 'kiosk', 'printer', 'scanner', 'catalog_only'];

    private function __construct() {}
}
