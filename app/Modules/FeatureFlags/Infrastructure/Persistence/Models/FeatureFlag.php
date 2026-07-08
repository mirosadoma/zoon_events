<?php

namespace App\Modules\FeatureFlags\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeatureFlag extends Model
{
    protected $fillable = ['key', 'name', 'description', 'owner', 'value_type', 'default_value', 'status', 'security_class', 'created_by_user_id'];

    protected function casts(): array
    {
        return [
            'default_value' => 'json',
        ];
    }

    public function overrides(): HasMany
    {
        return $this->hasMany(FeatureFlagOverride::class);
    }
}
