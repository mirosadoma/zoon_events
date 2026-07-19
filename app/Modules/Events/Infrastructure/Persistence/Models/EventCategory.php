<?php

namespace App\Modules\Events\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventCategory extends Model
{
    protected $fillable = [
        'event_id',
        'category_template_id',
        'name',
        'name_ar',
        'slug',
        'color',
        'capacity',
        'sort_order',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(CategoryTemplate::class, 'category_template_id');
    }

    public function privileges(): HasMany
    {
        return $this->hasMany(EventCategoryPrivilege::class);
    }
}
