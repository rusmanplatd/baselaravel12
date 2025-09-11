<?php

namespace App\Traits;

use App\Models\Auth\Permission;
use App\Models\Auth\PermissionScope;
use App\Models\Auth\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\Exceptions\RoleDoesNotExist;

trait HasScopedPermissions
{
    /**
     * Check if user has permission within a specific scope
     */
    public function hasPermissionInScope(string $permission, string $scopeType, string $scopeId): bool
    {
        $permissions = $this->getPermissionsForScope($scopeType, $scopeId);
        
        return $permissions->contains('name', $permission);
    }

    /**
     * Check if user has role within a specific scope
     */
    public function hasRoleInScope(string $role, string $scopeType, string $scopeId): bool
    {
        $roles = $this->getRolesForScope($scopeType, $scopeId);
        
        return $roles->contains('name', $role);
    }

    /**
     * Check if user has any of the given permissions within a scope
     */
    public function hasAnyPermissionInScope(array $permissions, string $scopeType, string $scopeId): bool
    {
        $userPermissions = $this->getPermissionsForScope($scopeType, $scopeId);
        
        return $userPermissions->whereIn('name', $permissions)->isNotEmpty();
    }

    /**
     * Check if user has all the given permissions within a scope
     */
    public function hasAllPermissionsInScope(array $permissions, string $scopeType, string $scopeId): bool
    {
        $userPermissions = $this->getPermissionsForScope($scopeType, $scopeId);
        $userPermissionNames = $userPermissions->pluck('name')->toArray();
        
        return empty(array_diff($permissions, $userPermissionNames));
    }

    /**
     * Get all permissions for user within a specific scope (including inherited)
     */
    public function getPermissionsForScope(string $scopeType, string $scopeId): Collection
    {
        $cacheKey = "user_permissions_{$this->getKey()}_{$scopeType}_{$scopeId}";
        
        return cache()->remember($cacheKey, config('permission.cache.expiration_time'), function () use ($scopeType, $scopeId) {
            // Get direct permissions
            $directPermissions = $this->permissions()
                ->inheritable($scopeType, $scopeId)
                ->get();

            // Get permissions through roles
            $rolePermissions = Permission::query()
                ->whereHas('roles.users', function ($query) {
                    $query->where($this->getForeignKey(), $this->getKey());
                })
                ->inheritable($scopeType, $scopeId)
                ->get();

            return $directPermissions->merge($rolePermissions)->unique('id');
        });
    }

    /**
     * Get all roles for user within a specific scope (including inherited)
     */
    public function getRolesForScope(string $scopeType, string $scopeId): Collection
    {
        $cacheKey = "user_roles_{$this->getKey()}_{$scopeType}_{$scopeId}";
        
        return cache()->remember($cacheKey, config('permission.cache.expiration_time'), function () use ($scopeType, $scopeId) {
            return $this->roles()
                ->inheritable($scopeType, $scopeId)
                ->get();
        });
    }

    /**
     * Assign permission to user within a specific scope
     */
    public function givePermissionInScope(string $permission, string $scopeType, string $scopeId): self
    {
        $permissionModel = Permission::findByName($permission);
        
        if (!$permissionModel) {
            throw PermissionDoesNotExist::named($permission);
        }

        $this->permissions()->attach($permissionModel->id, [
            'scope_type' => $scopeType,
            'scope_id' => $scopeId,
            'scope_path' => $this->buildScopePath($scopeType, $scopeId),
            'team_id' => $this->getTeamIdForScope($scopeType, $scopeId),
        ]);

        $this->forgetCachedPermissions();
        
        return $this;
    }

    /**
     * Remove permission from user within a specific scope
     */
    public function revokePermissionInScope(string $permission, string $scopeType, string $scopeId): self
    {
        $permissionModel = Permission::findByName($permission);
        
        if (!$permissionModel) {
            throw PermissionDoesNotExist::named($permission);
        }

        $this->permissions()
            ->wherePivot('permission_id', $permissionModel->id)
            ->wherePivot('scope_type', $scopeType)
            ->wherePivot('scope_id', $scopeId)
            ->detach();

        $this->forgetCachedPermissions();
        
        return $this;
    }

    /**
     * Assign role to user within a specific scope
     */
    public function assignRoleInScope(string $role, string $scopeType, string $scopeId): self
    {
        $roleModel = Role::findByName($role);
        
        if (!$roleModel) {
            throw RoleDoesNotExist::named($role);
        }

        $this->roles()->attach($roleModel->id, [
            'scope_type' => $scopeType,
            'scope_id' => $scopeId,
            'scope_path' => $this->buildScopePath($scopeType, $scopeId),
            'team_id' => $this->getTeamIdForScope($scopeType, $scopeId),
        ]);

        $this->forgetCachedPermissions();
        
        return $this;
    }

    /**
     * Remove role from user within a specific scope
     */
    public function removeRoleInScope(string $role, string $scopeType, string $scopeId): self
    {
        $roleModel = Role::findByName($role);
        
        if (!$roleModel) {
            throw RoleDoesNotExist::named($role);
        }

        $this->roles()
            ->wherePivot('role_id', $roleModel->id)
            ->wherePivot('scope_type', $scopeType)
            ->wherePivot('scope_id', $scopeId)
            ->detach();

        $this->forgetCachedPermissions();
        
        return $this;
    }

    /**
     * Get all scopes where user has a specific permission
     */
    public function getScopesWithPermission(string $permission): SupportCollection
    {
        $permissionModel = Permission::findByName($permission);
        
        if (!$permissionModel) {
            return collect();
        }

        // Get scopes from direct permissions
        $directScopes = $this->permissions()
            ->where('permission_id', $permissionModel->id)
            ->get()
            ->map(function ($pivot) {
                return [
                    'type' => $pivot->scope_type,
                    'id' => $pivot->scope_id,
                    'path' => $pivot->scope_path,
                    'source' => 'direct'
                ];
            });

        // Get scopes from role permissions
        $roleScopes = collect();
        $userRoles = $this->roles()->get();
        
        foreach ($userRoles as $role) {
            if ($role->hasPermissionTo($permission)) {
                $roleScopes->push([
                    'type' => $role->pivot->scope_type,
                    'id' => $role->pivot->scope_id,
                    'path' => $role->pivot->scope_path,
                    'source' => 'role',
                    'role' => $role->name
                ]);
            }
        }

        return $directScopes->merge($roleScopes)->unique(function ($item) {
            return $item['type'] . ':' . $item['id'];
        });
    }

    /**
     * Get all permissions across all scopes
     */
    public function getAllScopedPermissions(): SupportCollection
    {
        $permissions = collect();

        // Get all user's permission assignments with scope info
        $directPermissions = $this->permissions()
            ->get()
            ->map(function ($permission) {
                return [
                    'permission' => $permission->name,
                    'scope_type' => $permission->pivot->scope_type,
                    'scope_id' => $permission->pivot->scope_id,
                    'scope_path' => $permission->pivot->scope_path,
                    'source' => 'direct',
                    'is_global' => empty($permission->pivot->scope_type)
                ];
            });

        // Get permissions through roles
        $rolePermissions = collect();
        $userRoles = $this->roles()->get();
        
        foreach ($userRoles as $role) {
            foreach ($role->permissions as $permission) {
                $rolePermissions->push([
                    'permission' => $permission->name,
                    'scope_type' => $role->pivot->scope_type,
                    'scope_id' => $role->pivot->scope_id,
                    'scope_path' => $role->pivot->scope_path,
                    'source' => 'role',
                    'role' => $role->name,
                    'is_global' => empty($role->pivot->scope_type)
                ]);
            }
        }

        return $directPermissions->merge($rolePermissions);
    }

    /**
     * Build scope path for inheritance
     */
    protected function buildScopePath(string $scopeType, string $scopeId): ?array
    {
        $permissionScope = PermissionScope::where('scope_type', $scopeType)
            ->where('scope_id', $scopeId)
            ->first();
            
        return $permissionScope ? $permissionScope->scope_path : null;
    }

    /**
     * Get team ID for scope (for backward compatibility with Spatie teams feature)
     */
    protected function getTeamIdForScope(string $scopeType, string $scopeId): ?string
    {
        // For organization scope, return the organization ID as team_id
        if ($scopeType === 'organization') {
            return $scopeId;
        }

        // For other scopes, try to find parent organization
        $permissionScope = PermissionScope::where('scope_type', $scopeType)
            ->where('scope_id', $scopeId)
            ->first();

        if ($permissionScope && !empty($permissionScope->scope_path)) {
            foreach ($permissionScope->scope_path as $pathItem) {
                if ($pathItem['type'] === 'organization') {
                    return $pathItem['id'];
                }
            }
        }

        return null;
    }

    /**
     * Scope query to users with specific permission in scope
     */
    public function scopeWithPermissionInScope(Builder $query, string $permission, string $scopeType, string $scopeId): Builder
    {
        return $query->whereHas('permissions', function ($q) use ($permission, $scopeType, $scopeId) {
            $q->where('name', $permission)
              ->where('scope_type', $scopeType)
              ->where('scope_id', $scopeId);
        })->orWhereHas('roles.permissions', function ($q) use ($permission, $scopeType, $scopeId) {
            $q->where('name', $permission);
        })->whereHas('roles', function ($q) use ($scopeType, $scopeId) {
            $q->where('scope_type', $scopeType)
              ->where('scope_id', $scopeId);
        });
    }

    /**
     * Scope query to users with specific role in scope
     */
    public function scopeWithRoleInScope(Builder $query, string $role, string $scopeType, string $scopeId): Builder
    {
        return $query->whereHas('roles', function ($q) use ($role, $scopeType, $scopeId) {
            $q->where('name', $role)
              ->where('scope_type', $scopeType)  
              ->where('scope_id', $scopeId);
        });
    }
}