<?php

namespace App\Modules\Events\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryTemplatePrivilege extends Model
{
    protected $fillable = [
        'category_template_id',
        'privilege_id',
        'effect',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(CategoryTemplate::class, 'category_template_id');
    }

    public function privilege(): BelongsTo
    {
        return $this->belongsTo(Privilege::class);
    }
}
