<?php

namespace App\Modules\Authorization\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class PlatformRolePermission extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'platform_role_permissions';

    protected $guarded = ['*'];
}
