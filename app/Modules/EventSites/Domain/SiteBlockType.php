<?php

namespace App\Modules\EventSites\Domain;

enum SiteBlockType: string
{
    case Header = 'header';
    case Hero = 'hero';
    case About = 'about';
    case Agenda = 'agenda';
    case Speakers = 'speakers';
    case Venue = 'venue';
    case Faq = 'faq';
    case Sponsors = 'sponsors';
    case Gallery = 'gallery';
    case Section = 'section';
    case MediaText = 'media_text';
    case ImageShowcase = 'image_showcase';
    case Form = 'form';
    case RegisterCta = 'register_cta';
    case Footer = 'footer';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    public static function isValid(string $value): bool
    {
        return self::tryFrom($value) !== null;
    }
}
