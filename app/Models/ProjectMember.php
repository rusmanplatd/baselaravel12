<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectMember extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'project_id',
        'user_id',
        'role',
        'permissions',
        'added_by',
        'added_at',
    ];

    protected $casts = [
        'permissions' => 'array',
        'added_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    public function scopeAdmins($query)
    {
        return $query->where('role', 'admin');
    }

    public function scopeWriters($query)
    {
        return $query->where('role', 'write');
    }

    public function scopeReaders($query)
    {
        return $query->where('role', 'read');
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function canWrite(): bool
    {
        return in_array($this->role, ['admin', 'write']);
    }

    public function canRead(): bool
    {
        return in_array($this->role, ['admin', 'write', 'read']);
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        $permissions = $this->permissions ?? [];
        return in_array($permission, $permissions);
    }

    public function updateRole(string $role): void
    {
        $this->update(['role' => $role]);
    }

    public function addPermission(string $permission): void
    {
        $permissions = $this->permissions ?? [];
        
        if (!in_array($permission, $permissions)) {
            $permissions[] = $permission;
            $this->update(['permissions' => $permissions]);
        }
    }

    public function removePermission(string $permission): void
    {
        $permissions = $this->permissions ?? [];
        $permissions = array_values(array_diff($permissions, [$permission]));
        $this->update(['permissions' => $permissions]);
    }

    public function getRoleDisplayName(): string
    {
        return match ($this->role) {
            'admin' => 'Admin',
            'write' => 'Write',
            'read' => 'Read',
            default => 'Unknown',
        };
    }

    public function getPermissionsList(): array
    {
        $basePermissions = match ($this->role) {
            'admin' => ['read', 'write', 'admin', 'manage_members', 'manage_settings'],
            'write' => ['read', 'write'],
            'read' => ['read'],
            default => [],
        };

        return array_unique(array_merge($basePermissions, $this->permissions ?? []));
    }
}