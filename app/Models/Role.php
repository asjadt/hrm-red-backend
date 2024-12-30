<?php

namespace App\Models;

use Carbon\Carbon;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{

    protected $fillable = [
        'name',
        'guard_name',
        'business_id',
        'is_default',
        "is_system_default",
        "is_default_for_business",
        "description"

    ];
    protected $guard_name = 'api';

    // public function getCreatedAtAttribute($value)
    // {
    //     return (new Carbon($value))->format('d-m-Y');
    // }
    // public function getUpdatedAtAttribute($value)
    // {
    //     return (new Carbon($value))->format('d-m-Y');
    // }

}
