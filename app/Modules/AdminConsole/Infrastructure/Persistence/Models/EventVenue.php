<?php

namespace App\Modules\AdminConsole\Infrastructure\Persistence\Models;

use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Events\Infrastructure\Persistence\Models\EventAgendaItem;
use App\Modules\Events\Infrastructure\Persistence\Models\EventPath;
use App\Modules\Events\Infrastructure\Persistence\Models\EventVenueMap;
use App\Modules\Events\Infrastructure\Persistence\Models\EventZone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class EventVenue extends Model
{
    protected $fillable = [
        'tenant_id',
        'event_id',
        'country_id',
        'city_id',
        'name_en',
        'name_ar',
        'location_address',
        'latitude',
        'longitude',
        'start_at',
        'end_at',
        'registration_opens_at',
        'registration_closes_at',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'start_at' => 'immutable_datetime',
            'end_at' => 'immutable_datetime',
            'registration_opens_at' => 'immutable_datetime',
            'registration_closes_at' => 'immutable_datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function zones(): HasMany
    {
        return $this->hasMany(EventZone::class, 'venue_id')->orderBy('id');
    }

    public function paths(): HasMany
    {
        return $this->hasMany(EventPath::class, 'venue_id')->orderBy('sort_order')->orderBy('id');
    }

    public function map(): HasOne
    {
        return $this->hasOne(EventVenueMap::class, 'venue_id');
    }

    public function agendaItems(): HasMany
    {
        return $this->hasMany(EventAgendaItem::class, 'event_venue_id');
    }
}
