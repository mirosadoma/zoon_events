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
        'is_paid',
        'price_minor',
        'currency',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_paid' => 'boolean',
            'price_minor' => 'integer',
        ];
    }

    public function isPayable(): bool
    {
        return (bool) $this->is_paid && (int) $this->price_minor > 0;
    }

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

    public function venues(): HasMany
    {
        return $this->hasMany(EventCategoryVenue::class)->orderBy('sort_order');
    }
}
