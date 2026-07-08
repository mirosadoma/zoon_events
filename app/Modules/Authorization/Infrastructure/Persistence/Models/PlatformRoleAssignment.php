<?php

namespace App\Modules\Authorization\Infrastructure\Persistence\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformRoleAssignment extends Model
{
    protected $fillable = ['user_id', 'platform_role_id', 'granted_by_user_id', 'expires_at', 'revoked_at', 'revoked_by_user_id'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(PlatformRole::class, 'platform_role_id');
    }
}
