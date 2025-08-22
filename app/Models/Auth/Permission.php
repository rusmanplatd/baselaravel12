<?php

namespace App\Models\Auth;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    use HasUlids;

    protected $casts = [
        'id'         => 'string',
        'name'       => 'string',
        'guard_name' => 'string',
    ];
}
