<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class OrganizationUnit extends Model
{
    use HasUlids, LogsActivity, TenantScoped;

    protected $fillable = [
        'organization_id',
        'unit_code',
        'name',
        'unit_type',
        'description',
        'parent_unit_id',
        'responsibilities',
        'authorities',
        'is_active',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'responsibilities' => 'array',
        'authorities' => 'array',
        'is_active' => 'boolean',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function parentUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'parent_unit_id');
    }

    public function childUnits(): HasMany
    {
        return $this->hasMany(OrganizationUnit::class, 'parent_unit_id');
    }

    public function positions(): HasMany
    {
        return $this->hasMany(OrganizationPosition::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    public function getFullNameAttribute(): string
    {
        if ($this->parentUnit) {
            return $this->parentUnit->full_name.' - '.$this->name;
        }

        return $this->name;
    }

    public function getHierarchyLevelAttribute(): int
    {
        $level = 0;
        $parent = $this->parentUnit;
        while ($parent) {
            $level++;
            $parent = $parent->parentUnit;
        }

        return $level;
    }

    public function getAllAncestors()
    {
        $ancestors = collect();
        $parent = $this->parentUnit;
        while ($parent) {
            $ancestors->prepend($parent);
            $parent = $parent->parentUnit;
        }

        return $ancestors;
    }

    public function getAllDescendants()
    {
        $descendants = collect();
        foreach ($this->childUnits as $child) {
            $descendants->push($child);
            $descendants = $descendants->merge($child->getAllDescendants());
        }

        return $descendants;
    }

    public function isGovernanceUnit(): bool
    {
        return in_array($this->unit_type, [
            'board_of_commissioners',
            'board_of_directors',
            'executive_committee',
            'audit_committee',
            'risk_committee',
            'nomination_committee',
            'remuneration_committee',
        ]);
    }

    public function isOperationalUnit(): bool
    {
        return in_array($this->unit_type, [
            'division',
            'department',
            'section',
            'team',
            'branch_office',
            'representative_office',
        ]);
    }

    public function scopeGovernance($query)
    {
        return $query->whereIn('unit_type', [
            'board_of_commissioners',
            'board_of_directors',
            'executive_committee',
            'audit_committee',
            'risk_committee',
            'nomination_committee',
            'remuneration_committee',
        ]);
    }

    public function scopeOperational($query)
    {
        return $query->whereIn('unit_type', [
            'division',
            'department',
            'section',
            'team',
            'branch_office',
            'representative_office',
        ]);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->useLogName('organization')
            ->dontLogIfAttributesChangedOnly(['updated_at']);
    }
}
