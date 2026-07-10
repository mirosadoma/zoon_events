<?php

namespace App\Modules\FeatureFlags\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeatureFlagOverride extends Model
{
    protected $fillable = ['tenant_id', 'feature_flag_id', 'value', 'status', 'reason', 'created_by_user_id', 'expires_at'];

    protected function casts(): array
    {
        return [
            'value' => 'json',
            'expires_at' => 'datetime',
        ];
    }

    public function flag(): BelongsTo
    {
        return $this->belongsTo(FeatureFlag::class, 'feature_flag_id');
    }
}
