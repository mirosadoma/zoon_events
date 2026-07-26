<?php

namespace App\Modules\Events\Application\Support;

use App\Modules\Events\Domain\EventZoneType;
use App\Modules\Events\Infrastructure\Persistence\Models\EventAgendaItem;
use App\Modules\Events\Infrastructure\Persistence\Models\EventZone;

final class EventZonePresenter
{
    /**
     * @return array{
     *   id: string,
     *   venue_id: string,
     *   name: array{en: string, ar: string},
     *   zone_name_en: string,
     *   zone_name_ar: string,
     *   type: string,
     *   capacity: ?int
     * }
     */
    public static function toArray(EventZone $zone): array
    {
        $type = $zone->type instanceof EventZoneType ? $zone->type->value : (string) $zone->type;
        $nameEn = (string) $zone->zone_name_en;
        $nameAr = (string) $zone->zone_name_ar;

        return [
            'id' => (string) $zone->id,
            'venue_id' => (string) $zone->venue_id,
            'name' => ['en' => $nameEn, 'ar' => $nameAr],
            'zone_name_en' => $nameEn,
            'zone_name_ar' => $nameAr,
            'type' => $type,
            'capacity' => $zone->capacity !== null ? (int) $zone->capacity : null,
        ];
    }

    /**
     * @return array{
     *   id: string,
     *   event_venue_id: ?string,
     *   zone_id: ?string,
     *   agenda_date: ?string,
     *   title_en: string,
     *   title_ar: string,
     *   description_en: string,
     *   description_ar: string,
     *   speaker: ?string,
     *   start_at: ?string,
     *   end_at: ?string,
     *   sort_order?: int
     * }
     */
    public static function agendaItemForTenant(EventAgendaItem $item, string $timezone): array
    {
        return [
            'id' => (string) $item->id,
            'event_venue_id' => $item->event_venue_id ? (string) $item->event_venue_id : null,
            'zone_id' => $item->zone_id ? (string) $item->zone_id : null,
            'agenda_date' => $item->agenda_date?->toDateString(),
            'title_en' => $item->title_en,
            'title_ar' => $item->title_ar,
            'description_en' => $item->description_en ?? '',
            'description_ar' => $item->description_ar ?? '',
            'speaker' => $item->speaker,
            'start_at' => EventWallClockDateTime::toInput($item->start_at, $timezone),
            'end_at' => EventWallClockDateTime::toInput($item->end_at, $timezone),
            'sort_order' => (int) $item->sort_order,
            'venue_name' => $item->relationLoaded('venue') && $item->venue
                ? ['en' => (string) $item->venue->name_en, 'ar' => (string) $item->venue->name_ar]
                : null,
            'zone_name' => $item->relationLoaded('zone') && $item->zone
                ? ['en' => (string) $item->zone->zone_name_en, 'ar' => (string) $item->zone->zone_name_ar]
                : null,
        ];
    }

    /**
     * @return array{
     *   id: string,
     *   event_venue_id: ?string,
     *   zone_id: ?string,
     *   agenda_date: ?string,
     *   title: array{en: string, ar: string},
     *   description: array{en: string, ar: string},
     *   speaker: ?string,
     *   start_at: ?string,
     *   end_at: ?string,
     *   sort_order: int
     * }
     */
    public static function agendaItemForApi(EventAgendaItem $item, string $timezone): array
    {
        $tenant = self::agendaItemForTenant($item, $timezone);

        return [
            'id' => $tenant['id'],
            'event_venue_id' => $tenant['event_venue_id'],
            'zone_id' => $tenant['zone_id'],
            'agenda_date' => $tenant['agenda_date'],
            'title' => ['en' => $tenant['title_en'], 'ar' => $tenant['title_ar']],
            'description' => ['en' => $tenant['description_en'], 'ar' => $tenant['description_ar']],
            'speaker' => $tenant['speaker'],
            'start_at' => $tenant['start_at'],
            'end_at' => $tenant['end_at'],
            'sort_order' => $tenant['sort_order'],
        ];
    }

    /**
     * @return array{
     *   id: string,
     *   title: array{en: string, ar: string},
     *   start_at: ?string,
     *   end_at: ?string,
     *   agenda_date: ?string,
     *   event_venue_id: ?string,
     *   zone_id: ?string,
     *   speaker: ?string,
     *   venue_name: ?array{en: string, ar: string},
     *   zone_name: ?array{en: string, ar: string},
     *   sort_order: int
     * }
     */
    public static function agendaItemForPublic(EventAgendaItem $item, string $timezone, ?string $fallbackVenueId = null): array
    {
        $agendaDate = $item->agenda_date?->toDateString()
            ?? ($item->start_at?->toDateString());
        $venueId = $item->event_venue_id !== null
            ? (string) $item->event_venue_id
            : $fallbackVenueId;

        return [
            'id' => (string) $item->id,
            'title' => ['en' => $item->title_en, 'ar' => $item->title_ar],
            'start_at' => EventWallClockDateTime::toIso8601($item->start_at, $timezone),
            'end_at' => EventWallClockDateTime::toIso8601($item->end_at, $timezone),
            'agenda_date' => $agendaDate,
            'event_venue_id' => $venueId,
            'zone_id' => $item->zone_id !== null ? (string) $item->zone_id : null,
            'speaker' => $item->speaker,
            'venue_name' => $item->venue
                ? ['en' => (string) $item->venue->name_en, 'ar' => (string) $item->venue->name_ar]
                : null,
            'zone_name' => $item->zone
                ? ['en' => (string) $item->zone->zone_name_en, 'ar' => (string) $item->zone->zone_name_ar]
                : null,
            'sort_order' => (int) $item->sort_order,
        ];
    }
}
