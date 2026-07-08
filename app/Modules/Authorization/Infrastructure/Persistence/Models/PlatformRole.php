<?php

namespace App\Modules\Authorization\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PlatformRole extends Model
{
    protected $fillable = ['name', 'description', 'is_system', 'created_by_user_id'];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'platform_role_permissions')
            ->withPivot(['granted_by_user_id', 'created_at']);
    }
}
