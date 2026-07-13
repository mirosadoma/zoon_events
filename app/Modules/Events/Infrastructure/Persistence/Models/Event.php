<?php

namespace App\Modules\Events\Infrastructure\Persistence\Models;

use App\Modules\AdminConsole\Infrastructure\Persistence\Models\EventVenue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class Event extends Model
{
    protected $fillable = [
        'tenant_id', 'slug', 'name_en', 'name_ar', 'description_en', 'description_ar',
        'tier', 'event_type', 'registration_mode', 'status', 'timezone', 'start_at', 'end_at', 'registration_opens_at',
        'registration_closes_at', 'location_name_en', 'location_name_ar',
        'location_address_en', 'location_address_ar', 'capacity', 'main_image_path',
        'active_form_version_id', 'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'immutable_datetime',
            'end_at' => 'immutable_datetime',
            'registration_opens_at' => 'immutable_datetime',
            'registration_closes_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'archived_at' => 'immutable_datetime',
        ];
    }

    public function branding(): HasOne
    {
        return $this->hasOne(EventBranding::class);
    }

    public function venues(): HasMany
    {
        return $this->hasMany(EventVenue::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(EventImage::class)->orderBy('sort_order')->orderBy('id');
    }

    public function agendaItems(): HasMany
    {
        return $this->hasMany(EventAgendaItem::class)->orderBy('sort_order')->orderBy('start_at');
    }
}
