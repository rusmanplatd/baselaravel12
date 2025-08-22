<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class OrganizationMembership extends Model
{
    use HasUlids;
    protected $fillable = [
        'user_id',
        'organization_id',
        'organization_unit_id',
        'organization_position_id',
        'membership_type',
        'start_date',
        'end_date',
        'status',
        'additional_roles',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'additional_roles' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function organizationUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class);
    }

    public function organizationPosition(): BelongsTo
    {
        return $this->belongsTo(OrganizationPosition::class);
    }

    public function getDurationAttribute(): string
    {
        $start = $this->start_date;
        $end = $this->end_date ?? now();

        $duration = $start->diff($end);

        if ($duration->y > 0) {
            return $duration->y . ' years, ' . $duration->m . ' months';
        } elseif ($duration->m > 0) {
            return $duration->m . ' months, ' . $duration->d . ' days';
        } else {
            return $duration->d . ' days';
        }
    }

    public function getDurationInYearsAttribute(): float
    {
        $start = $this->start_date;
        $end = $this->end_date ?? now();

        return round($start->diffInDays($end) / 365.25, 2);
    }

    public function isActive(): bool
    {
        return $this->status === 'active' &&
               $this->start_date <= now() &&
               (!$this->end_date || $this->end_date >= now());
    }

    public function isExpired(): bool
    {
        return $this->end_date && $this->end_date < now();
    }

    public function isUpcoming(): bool
    {
        return $this->start_date > now();
    }

    public function isBoardMembership(): bool
    {
        return $this->membership_type === 'board_member' ||
               ($this->organizationPosition && $this->organizationPosition->isBoardPosition());
    }

    public function isExecutiveMembership(): bool
    {
        return $this->organizationPosition && $this->organizationPosition->isExecutivePosition();
    }

    public function isManagementMembership(): bool
    {
        return $this->organizationPosition && $this->organizationPosition->isManagementPosition();
    }

    public function getFullPositionTitleAttribute(): string
    {
        if ($this->organizationPosition) {
            return $this->organizationPosition->full_title;
        }

        if ($this->organizationUnit) {
            return ucfirst(str_replace('_', ' ', $this->membership_type)) . ' - ' . $this->organizationUnit->name;
        }

        return ucfirst(str_replace('_', ' ', $this->membership_type));
    }

    public function activate(): void
    {
        $this->update(['status' => 'active']);
    }

    public function deactivate(): void
    {
        $this->update(['status' => 'inactive']);
    }

    public function terminate(?Carbon $endDate = null): void
    {
        $this->update([
            'status' => 'terminated',
            'end_date' => $endDate ?? now()
        ]);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active')
                    ->where('start_date', '<=', now())
                    ->where(function ($q) {
                        $q->whereNull('end_date')
                          ->orWhere('end_date', '>=', now());
                    });
    }

    public function scopeInactive($query)
    {
        return $query->where('status', 'inactive');
    }

    public function scopeTerminated($query)
    {
        return $query->where('status', 'terminated');
    }

    public function scopeExpired($query)
    {
        return $query->whereNotNull('end_date')
                    ->where('end_date', '<', now());
    }

    public function scopeUpcoming($query)
    {
        return $query->where('start_date', '>', now());
    }

    public function scopeBoard($query)
    {
        return $query->where('membership_type', 'board_member')
                    ->orWhereHas('organizationPosition', function ($q) {
                        $q->where('position_level', 'board_member');
                    });
    }

    public function scopeExecutive($query)
    {
        return $query->whereHas('organizationPosition', function ($q) {
            $q->where('position_level', 'c_level');
        });
    }

    public function scopeManagement($query)
    {
        return $query->whereHas('organizationPosition', function ($q) {
            $q->whereIn('position_level', [
                'c_level',
                'vice_president',
                'director',
                'senior_manager',
                'manager'
            ]);
        });
    }
}
