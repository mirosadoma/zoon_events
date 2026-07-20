<?php

namespace App\Modules\Events\Infrastructure\Persistence\Models;

use App\Modules\Tenancy\Infrastructure\Persistence\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        return $this->hasMany(CategoryTemplatePrivilege::class)->orderBy('id');
    }

    public function privilegeCatalog(): BelongsToMany
    {
        return $this->belongsToMany(
            Privilege::class,
            'category_template_privileges',
            'category_template_id',
            'privilege_id',
        )->withPivot(['effect'])->withTimestamps();
    }

    public function eventCategories(): HasMany
    {
        return $this->hasMany(EventCategory::class, 'category_template_id');
    }
}
