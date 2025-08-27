<?php

namespace App\Models\Auth;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use HasUlids, HasFactory;

    protected $fillable = [
        'name',
        'guard_name',
        'team_id',
        'type',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'id' => 'string',
        'name' => 'string',
        'guard_name' => 'string',
        'type' => 'int',
        'created_by' => 'string',
        'updated_by' => 'string',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (Auth::check()) {
                $model->created_by = Auth::id();
                $model->updated_by = Auth::id();
            }
        });

        static::updating(function ($model) {
            if (Auth::check()) {
                $model->updated_by = Auth::id();
            }
        });
    }
}
