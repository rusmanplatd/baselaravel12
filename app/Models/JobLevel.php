<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobLevel extends Model
{
    protected $fillable = [
        'name',
        'description',
        'level_order',
        'min_salary',
        'max_salary',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'min_salary' => 'decimal:2',
        'max_salary' => 'decimal:2',
    ];

    public function jobPositions(): HasMany
    {
        return $this->hasMany(JobPosition::class);
    }
}
