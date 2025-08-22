<?php

namespace App\Models\Auth;

use App\Models\Globals\Activity;
use App\Models\Globals\ApprovalDetail;
use App\Models\Globals\MenuFlow;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

class Role extends SpatieRole
{
    use HasUlids;

    protected $fillable = [
        'name',
        'guard_name',
        'type',
    ];

    protected $casts = [
        'id'         => 'string',
        'name'       => 'string',
        'guard_name' => 'string',
        'type'       => 'int',

        'created_by' => 'string',
        'updated_by' => 'string',
    ];
}
