<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrganizationPositionLevel extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'organization_position_levels';

    protected $fillable = [
        'code',
        'name',
        'description',
        'hierarchy_level',
        'is_active',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'hierarchy_level' => 'integer',
        'sort_order' => 'integer',
    ];

    public function organizationPositions(): HasMany
    {
        return $this->hasMany(OrganizationPosition::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('hierarchy_level');
    }
}
