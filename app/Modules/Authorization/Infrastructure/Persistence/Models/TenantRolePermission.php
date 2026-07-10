<?php

namespace App\Modules\Authorization\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class TenantRolePermission extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'tenant_role_permissions';

    protected $guarded = ['*'];
}
