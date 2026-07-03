<?php

namespace App\Modules\Authorization\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    use HasUlids;

    protected $fillable = ['key', 'module', 'description', 'scope', 'risk_level'];
}
