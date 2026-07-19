<?php

namespace App\Modules\Events\Infrastructure\Persistence\Models;

use App\Modules\Tenancy\Infrastructure\Persistence\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Privilege extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'key',
        'label',
        'label_ar',
        'effect',
        'target_type',
        'target_id',
        'sort_order',
    ];

    public function templateLinks(): HasMany
    {
        return $this->hasMany(CategoryTemplatePrivilege::class);
    }

    public function templates(): BelongsToMany
    {
        return $this->belongsToMany(
            CategoryTemplate::class,
            'category_template_privileges',
            'privilege_id',
            'category_template_id',
        )->withPivot(['effect'])->withTimestamps();
    }
}
