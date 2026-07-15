<?php

namespace App\Modules\Tenancy\Domain;

enum OrganizationType: string
{
    case Organizer = 'organizer';
    case VenueOwner = 'venue_owner';
    case Hybrid = 'hybrid';

    public function mayOwnVenues(): bool
    {
        return $this === self::VenueOwner || $this === self::Hybrid;
    }

    public function mayRequestRentals(): bool
    {
        return $this === self::Organizer || $this === self::Hybrid;
    }
}
