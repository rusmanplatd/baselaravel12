<?php

namespace App\Models\Auth;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use HasUlids;

    protected $fillable = [
        'name',
        'guard_name',
        'type',
    ];

    protected $casts = [
        'id' => 'string',
        'name' => 'string',
        'guard_name' => 'string',
        'type' => 'int',

        'created_by' => 'string',
        'updated_by' => 'string',
    ];
}
