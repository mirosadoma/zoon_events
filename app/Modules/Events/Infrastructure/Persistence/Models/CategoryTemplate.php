<?php

namespace App\Modules\Events\Infrastructure\Persistence\Models;

use App\Modules\Tenancy\Infrastructure\Persistence\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoryTemplate extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'name_ar',
        'slug',
        'color',
        'sort_order',
    ];

    public function privileges(): HasMany
    {
        return $this->hasMany(CategoryTemplatePrivilege::class);
    }
}
