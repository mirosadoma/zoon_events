<?php

namespace App\Modules\AdminConsole\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class Timezone extends Model
{
    protected $fillable = [
        'identifier',
        'name_en',
        'name_ar',
        'region_en',
        'region_ar',
        'country_en',
        'country_ar',
        'utc_offset',
    ];
}
