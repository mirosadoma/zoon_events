<?php

namespace App\Modules\Events\Domain;

enum EventZoneShapeType: string
{
    case Polygon = 'polygon';
    case Rectangle = 'rectangle';
    case Circle = 'circle';
    case Triangle = 'triangle';
    case Hexagon = 'hexagon';
    case Ellipse = 'ellipse';
    case Pillar = 'pillar';
    case Person = 'person';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $type): string => $type->value, self::cases());
    }
}
