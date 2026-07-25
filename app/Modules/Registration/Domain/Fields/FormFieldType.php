<?php

namespace App\Modules\Registration\Domain\Fields;

enum FormFieldType: string
{
    case Text = 'text';
    case Email = 'email';
    case Phone = 'phone';
    case Select = 'select';
    case Number = 'number';
    case Date = 'date';
    case MultiSelect = 'multi_select';
    case Radio = 'radio';
    case Checkbox = 'checkbox';
    case Consent = 'consent';
    case Hidden = 'hidden';

    // Layout / display-only blocks (no submission values)
    case Heading = 'heading';
    case Divider = 'divider';
    case Paragraph = 'paragraph';
    case EventLogo = 'event_logo';
    case EventName = 'event_name';
    case EventVenue = 'event_venue';
    case EventDates = 'event_dates';
    case EventDescription = 'event_description';
    case EventCategories = 'event_categories';
    case EventVenueSelect = 'event_venue_select';

    public function isDisplayOnly(): bool
    {
        return match ($this) {
            self::Heading,
            self::Divider,
            self::Paragraph,
            self::EventLogo,
            self::EventName,
            self::EventVenue,
            self::EventDates,
            self::EventDescription,
            self::EventCategories,
            self::EventVenueSelect => true,
            default => false,
        };
    }
}
