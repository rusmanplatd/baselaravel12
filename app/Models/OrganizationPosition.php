<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class OrganizationPosition extends Model
{
    use HasFactory, HasUlids, LogsActivity, TenantScoped;

    protected $fillable = [
        'organization_id',
        'organization_unit_id',
        'position_code',
        'organization_position_level_id',
        'title',
        'job_description',
        'qualifications',
        'responsibilities',
        'min_salary',
        'max_salary',
        'is_active',
        'max_incumbents',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'qualifications' => 'array',
        'responsibilities' => 'array',
        'min_salary' => 'decimal:2',
        'max_salary' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function organizationUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class);
    }

    public function organizationPositionLevel(): BelongsTo
    {
        return $this->belongsTo(OrganizationPositionLevel::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    public function activeMemberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class)
            ->where('status', 'active');
    }

    public function getCurrentIncumbents()
    {
        return $this->activeMemberships()
            ->with('user')
            ->get()
            ->pluck('user');
    }

    public function getAvailableSlots(): int
    {
        return $this->max_incumbents - $this->activeMemberships()->count();
    }

    public function hasAvailableSlots(): bool
    {
        return $this->getAvailableSlots() > 0;
    }

    public function isFullyOccupied(): bool
    {
        return $this->getAvailableSlots() <= 0;
    }

    public function isBoardPosition(): bool
    {
        return $this->organizationPositionLevel->code === 'board_member';
    }

    public function isExecutivePosition(): bool
    {
        return $this->organizationPositionLevel->code === 'c_level';
    }

    public function isManagementPosition(): bool
    {
        return in_array($this->organizationPositionLevel->code, [
            'c_level',
            'vice_president',
            'director',
            'senior_manager',
            'manager',
        ]);
    }

    public function getSalaryRangeAttribute(): string
    {
        if ($this->min_salary && $this->max_salary) {
            return number_format($this->min_salary, 0).' - '.number_format($this->max_salary, 0);
        } elseif ($this->min_salary) {
            return 'Min: '.number_format($this->min_salary, 0);
        } elseif ($this->max_salary) {
            return 'Max: '.number_format($this->max_salary, 0);
        }

        return 'Not specified';
    }

    public function getFullTitleAttribute(): string
    {
        if ($this->organizationUnit) {
            return $this->title.' - '.$this->organizationUnit->name;
        }

        return $this->title;
    }

    public function scopeBoard($query)
    {
        return $query->whereHas('organizationPositionLevel', function ($q) {
            $q->where('code', 'board_member');
        });
    }

    public function scopeExecutive($query)
    {
        return $query->whereHas('organizationPositionLevel', function ($q) {
            $q->where('code', 'c_level');
        });
    }

    public function scopeManagement($query)
    {
        return $query->whereHas('organizationPositionLevel', function ($q) {
            $q->whereIn('code', [
                'c_level',
                'vice_president',
                'director',
                'senior_manager',
                'manager',
            ]);
        });
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeAvailable($query)
    {
        return $query->where(function ($q) {
            $q->whereDoesntHave('activeMemberships')
                ->orWhereHas('activeMemberships', function ($subQ) {
                    $subQ->havingRaw('COUNT(*) < organization_positions.max_incumbents');
                });
        });
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
