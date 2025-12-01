<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class ProjectIteration extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'project_id',
        'title',
        'description', 
        'start_date',
        'end_date',
        'status',
        'duration_weeks',
        'goals',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'goals' => 'array',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProjectItem::class, 'iteration_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopePlanned($query)
    {
        return $query->where('status', 'planned');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeCurrent($query)
    {
        $now = Carbon::now()->toDateString();
        return $query->where('start_date', '<=', $now)
                    ->where('end_date', '>=', $now)
                    ->where('status', 'active');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isCurrent(): bool
    {
        $now = Carbon::now();
        return $this->start_date <= $now && $this->end_date >= $now && $this->isActive();
    }

    public function getDurationInDays(): int
    {
        return $this->start_date->diffInDays($this->end_date) + 1;
    }

    public function getRemainingDays(): int
    {
        if ($this->isCompleted()) {
            return 0;
        }
        
        $now = Carbon::now();
        if ($now > $this->end_date) {
            return 0;
        }
        
        return $now->diffInDays($this->end_date);
    }

    public function getProgressPercentage(): float
    {
        $totalDays = $this->getDurationInDays();
        $elapsed = $this->start_date->diffInDays(Carbon::now()) + 1;
        
        if ($elapsed <= 0) {
            return 0;
        }
        
        if ($elapsed >= $totalDays) {
            return 100;
        }
        
        return round(($elapsed / $totalDays) * 100, 2);
    }

    public function getCompletionStats(): array
    {
        $totalItems = $this->items()->count();
        $completedItems = $this->items()->where('status', 'done')->count();
        
        return [
            'total' => $totalItems,
            'completed' => $completedItems,
            'percentage' => $totalItems > 0 ? round(($completedItems / $totalItems) * 100, 2) : 0,
        ];
    }

    public function start(): void
    {
        $this->update(['status' => 'active']);
    }

    public function complete(): void
    {
        $this->update(['status' => 'completed']);
    }

    public function cancel(): void
    {
        $this->update(['status' => 'cancelled']);
    }

    public function canEdit(User $user): bool
    {
        return $user->hasPermissionTo('projects:write', $this->project) ||
               $user->hasPermissionTo('projects:admin', $this->project);
    }

    public function canDelete(User $user): bool
    {
        return $user->hasPermissionTo('projects:delete', $this->project) ||
               $user->hasPermissionTo('projects:admin', $this->project);
    }
}