<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    protected $fillable = [
        'organization_id',
        'name',
        'description',
        'parent_department_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function parentDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'parent_department_id');
    }

    public function childDepartments(): HasMany
    {
        return $this->hasMany(Department::class, 'parent_department_id');
    }

    public function jobPositions(): HasMany
    {
        return $this->hasMany(JobPosition::class);
    }
}
