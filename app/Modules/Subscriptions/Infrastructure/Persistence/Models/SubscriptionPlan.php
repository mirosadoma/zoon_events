<?php

namespace App\Modules\Subscriptions\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    protected $fillable = [
        'name',
        'name_ar',
        'description',
        'description_ar',
        'is_trial',
        'is_active',
        'duration_days',
        'price',
        'currency',
        'max_events',
        'max_attendees',
        'max_devices',
        'allowed_features',
        'sort_order',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'is_trial' => 'boolean',
            'is_active' => 'boolean',
            'price' => 'decimal:2',
            'allowed_features' => 'array',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(TenantSubscription::class, 'plan_id');
    }

    public function isFree(): bool
    {
        return $this->is_trial || (float) $this->price === 0.0;
    }
}
