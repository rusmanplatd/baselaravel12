<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class Project extends Model
{
    use HasFactory, HasUlids, LogsActivity;

    protected $fillable = [
        'title',
        'description',
        'status',
        'visibility',
        'organization_id',
        'created_by',
        'updated_by',
        'settings',
        'closed_at',
    ];

    protected $casts = [
        'settings' => 'array',
        'closed_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(ProjectField::class)->orderBy('sort_order');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProjectItem::class)->orderBy('sort_order');
    }

    public function views(): HasMany
    {
        return $this->hasMany(ProjectView::class);
    }

    public function workflows(): HasMany
    {
        return $this->hasMany(ProjectWorkflow::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_members')
            ->withPivot(['role', 'permissions', 'added_by', 'added_at'])
            ->withTimestamps();
    }

    public function iterations(): HasMany
    {
        return $this->hasMany(ProjectIteration::class);
    }

    public function savedFilters(): HasMany
    {
        return $this->hasMany(ProjectSavedFilter::class);
    }

    public function insights(): HasMany
    {
        return $this->hasMany(ProjectInsight::class);
    }

    public function admins(): BelongsToMany
    {
        return $this->users()->wherePivot('role', 'admin');
    }

    public function writers(): BelongsToMany
    {
        return $this->users()->wherePivot('role', 'write');
    }

    public function readers(): BelongsToMany
    {
        return $this->users()->wherePivot('role', 'read');
    }

    public function defaultView(): HasMany
    {
        return $this->views()->where('is_default', true);
    }

    public function activeWorkflows(): HasMany
    {
        return $this->workflows()->where('is_active', true);
    }

    public function scopeForOrganization($query, $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeVisible($query, User $user)
    {
        return $query->where(function ($q) use ($user) {
            $q->where('visibility', 'public')
                ->orWhereHas('members', function ($memberQuery) use ($user) {
                    $memberQuery->where('user_id', $user->id);
                });
        });
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function isVisible(User $user): bool
    {
        if ($this->visibility === 'public') {
            return true;
        }

        return $this->members()->where('user_id', $user->id)->exists();
    }

    public function canEdit(User $user): bool
    {
        return $user->hasPermissionTo('project.edit', $this);
    }

    public function canAdmin(User $user): bool
    {
        return $user->hasPermissionTo('project.delete', $this) || 
               $user->hasPermissionTo('project.settings', $this);
    }

    public function userHasPermission(User $user, string $permission): bool
    {
        return $user->hasPermissionTo($permission, $this);
    }

    public function userHasRole(User $user, string $role): bool
    {
        return $user->hasRole($role, $this);
    }

    public function assignRoleToUser(User $user, string $roleName): void
    {
        $role = Role::where('name', $roleName)->first();
        if ($role) {
            $user->assignRole($role, $this);
        }
    }

    public function removeRoleFromUser(User $user, string $roleName): void
    {
        $role = Role::where('name', $roleName)->first();
        if ($role) {
            $user->removeRole($role, $this);
        }
    }

    public function givePermissionToUser(User $user, string $permissionName): void
    {
        $permission = Permission::where('name', $permissionName)->first();
        if ($permission) {
            $user->givePermissionTo($permission, $this);
        }
    }

    public function revokePermissionFromUser(User $user, string $permissionName): void
    {
        $permission = Permission::where('name', $permissionName)->first();
        if ($permission) {
            $user->revokePermissionTo($permission, $this);
        }
    }

    public function addMember(User $user, string $role = 'project.viewer', array $permissions = [], User $addedBy = null): ProjectMember
    {
        $member = $this->members()->create([
            'user_id' => $user->id,
            'role' => $role,
            'permissions' => $permissions,
            'added_by' => $addedBy?->id ?? auth()->id(),
        ]);

        // Assign scoped role to user
        $this->assignRoleToUser($user, $role);

        // Give additional permissions if specified
        foreach ($permissions as $permission) {
            $this->givePermissionToUser($user, $permission);
        }

        return $member;
    }

    public function removeMember(User $user): bool
    {
        // Remove scoped roles and permissions
        $member = $this->members()->where('user_id', $user->id)->first();
        if ($member) {
            // Remove the role
            $this->removeRoleFromUser($user, $member->role);
            
            // Remove any additional permissions
            foreach ($member->permissions ?? [] as $permission) {
                $this->revokePermissionFromUser($user, $permission);
            }
        }

        return $this->members()->where('user_id', $user->id)->delete() > 0;
    }

    public function close(): void
    {
        $this->update([
            'status' => 'closed',
            'closed_at' => now(),
        ]);
    }

    public function reopen(): void
    {
        $this->update([
            'status' => 'open',
            'closed_at' => null,
        ]);
    }

    public function initializeDefaultFields(): void
    {
        $defaultFields = [
            [
                'name' => 'Status',
                'type' => 'single_select',
                'options' => ['todo', 'in_progress', 'done'],
                'is_system' => true,
                'is_required' => true,
                'sort_order' => 0,
            ],
            [
                'name' => 'Assignees',
                'type' => 'assignees',
                'is_system' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Priority',
                'type' => 'single_select',
                'options' => ['low', 'medium', 'high', 'urgent'],
                'sort_order' => 2,
            ],
        ];

        foreach ($defaultFields as $fieldData) {
            $this->fields()->create($fieldData);
        }
    }

    public function initializeDefaultView(): void
    {
        $this->views()->create([
            'name' => 'Table',
            'layout' => 'table',
            'is_default' => true,
            'is_public' => true,
            'visible_fields' => ['title', 'status', 'assignees', 'priority'],
            'created_by' => $this->created_by,
        ]);
    }

    public function calendars()
    {
        return $this->morphMany(Calendar::class, 'calendarable');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'description', 'status', 'visibility'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Project {$eventName}")
            ->useLogName('project');
    }
}