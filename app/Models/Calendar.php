<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Calendar extends Model
{
    use HasFactory, HasUlids, LogsActivity;

    protected $fillable = [
        'name',
        'description',
        'color',
        'timezone',
        'calendarable_id',
        'calendarable_type',
        'visibility',
        'settings',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
    ];

    public function calendarable(): MorphTo
    {
        return $this->morphTo();
    }

    public function events(): HasMany
    {
        return $this->hasMany(CalendarEvent::class)->orderBy('starts_at');
    }

    public function activeEvents(): HasMany
    {
        return $this->events()->where('status', '!=', 'cancelled');
    }

    public function upcomingEvents(): HasMany
    {
        return $this->events()
            ->where('starts_at', '>=', now())
            ->where('status', '!=', 'cancelled')
            ->orderBy('starts_at');
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(CalendarPermission::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeVisible($query, User $user)
    {
        return $query->where(function ($q) use ($user) {
            $q->where('visibility', 'public')
                ->orWhere('created_by', $user->id)
                ->orWhereHas('permissions', function ($permQuery) use ($user) {
                    $permQuery->where('user_id', $user->id);
                });
        });
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function canView(User $user): bool
    {
        if ($this->visibility === 'public') {
            return true;
        }

        if ($this->created_by === $user->id) {
            return true;
        }

        return $this->permissions()->where('user_id', $user->id)->exists();
    }

    public function canEdit(User $user): bool
    {
        if ($this->created_by === $user->id) {
            return true;
        }

        $permission = $this->permissions()->where('user_id', $user->id)->first();
        return $permission && in_array($permission->permission, ['write', 'admin']);
    }

    public function canAdmin(User $user): bool
    {
        if ($this->created_by === $user->id) {
            return true;
        }

        $permission = $this->permissions()->where('user_id', $user->id)->first();
        return $permission && $permission->permission === 'admin';
    }

    public function shareWith(User $user, string $permission = 'read', User $grantedBy = null): CalendarPermission
    {
        return $this->permissions()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'permission' => $permission,
                'granted_by' => $grantedBy?->id ?? auth()->id(),
            ]
        );
    }

    public function revokeAccess(User $user): bool
    {
        return $this->permissions()->where('user_id', $user->id)->delete() > 0;
    }

    public function getOwnerNameAttribute(): string
    {
        return match ($this->calendarable_type) {
            User::class => $this->calendarable->name,
            Organization::class => $this->calendarable->name,
            Project::class => $this->calendarable->title,
            default => 'Unknown',
        };
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'description', 'color', 'visibility', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Calendar {$eventName}")
            ->useLogName('calendar');
    }
}
