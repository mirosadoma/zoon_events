<?php

namespace App\Modules\Authorization\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    protected $fillable = ['key', 'module', 'description', 'scope', 'risk_level'];
}
