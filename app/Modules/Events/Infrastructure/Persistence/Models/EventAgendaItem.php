<?php

namespace App\Modules\Events\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EventAgendaItem extends Model
{
    protected $fillable = [
        'tenant_id',
        'event_id',
        'event_venue_id',
        'zone_id',
        'agenda_date',
        'title_en',
        'title_ar',
        'description_en',
        'description_ar',
        'speaker',
        'start_at',
        'end_at',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'agenda_date' => 'immutable_date',
            'start_at' => 'immutable_datetime',
            'end_at' => 'immutable_datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\AdminConsole\Infrastructure\Persistence\Models\EventVenue::class, 'event_venue_id');
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(EventZone::class, 'zone_id');
    }

    /** @param  Builder<self>  $query */
    public function scopeForVenue(Builder $query, ?int $venueId): Builder
    {
        if ($venueId === null) {
            return $query;
        }

        return $query->where('event_venue_id', $venueId);
    }

    /** @param  Builder<self>  $query */
    public function scopeForZone(Builder $query, ?int $zoneId): Builder
    {
        if ($zoneId === null) {
            return $query;
        }

        return $query->where('zone_id', $zoneId);
    }

    /** @param  Builder<self>  $query */
    public function scopeForDate(Builder $query, ?string $date): Builder
    {
        if ($date === null || $date === '') {
            return $query;
        }

        return $query->whereDate('agenda_date', $date);
    }
}
