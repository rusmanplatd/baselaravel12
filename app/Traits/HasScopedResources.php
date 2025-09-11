<?php

namespace App\Traits;

use App\Models\Auth\Permission;
use App\Models\Auth\PermissionScope;
use App\Models\Auth\Role;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Collection;

trait HasScopedResources
{
    /**
     * Get all permissions scoped to this resource
     */
    public function scopedPermissions(): MorphMany
    {
        return $this->morphMany(Permission::class, 'scope');
    }

    /**
     * Get all roles scoped to this resource
     */
    public function scopedRoles(): MorphMany
    {
        return $this->morphMany(Role::class, 'scope');
    }

    /**
     * Get permission scope configuration for this resource
     */
    public function permissionScope(): HasMany
    {
        return $this->hasMany(PermissionScope::class, 'scope_id')
            ->where('scope_type', $this->getMorphClass());
    }

    /**
     * Create a permission scope for this resource
     */
    public function createPermissionScope(
        ?string $parentScopeType = null,
        ?string $parentScopeId = null,
        bool $inheritsPermissions = true,
        array $metadata = []
    ): PermissionScope {
        return PermissionScope::create([
            'scope_type' => $this->getMorphClass(),
            'scope_id' => $this->getKey(),
            'parent_scope_type' => $parentScopeType,
            'parent_scope_id' => $parentScopeId,
            'inherits_permissions' => $inheritsPermissions,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Get or create permission scope for this resource
     */
    public function getOrCreatePermissionScope(
        ?string $parentScopeType = null,
        ?string $parentScopeId = null,
        bool $inheritsPermissions = true,
        array $metadata = []
    ): PermissionScope {
        $scope = PermissionScope::where('scope_type', $this->getMorphClass())
            ->where('scope_id', $this->getKey())
            ->first();

        if (!$scope) {
            $scope = $this->createPermissionScope($parentScopeType, $parentScopeId, $inheritsPermissions, $metadata);
        }

        return $scope;
    }

    /**
     * Create a global permission (system-wide)
     */
    public function createGlobalPermission(string $name, string $guardName = 'web'): Permission
    {
        return Permission::create([
            'name' => $name,
            'guard_name' => $guardName,
            'is_global' => true,
        ]);
    }

    /**
     * Create a permission scoped to this resource
     */
    public function createScopedPermission(string $name, string $guardName = 'web'): Permission
    {
        return Permission::create([
            'name' => $name,
            'guard_name' => $guardName,
            'scope_type' => $this->getMorphClass(),
            'scope_id' => $this->getKey(),
            'is_global' => false,
        ]);
    }

    /**
     * Create a global role (system-wide)
     */
    public function createGlobalRole(string $name, string $guardName = 'web', array $permissions = []): Role
    {
        $role = Role::create([
            'name' => $name,
            'guard_name' => $guardName,
            'is_global' => true,
        ]);

        if (!empty($permissions)) {
            $role->givePermissionTo($permissions);
        }

        return $role;
    }

    /**
     * Create a role scoped to this resource
     */
    public function createScopedRole(string $name, string $guardName = 'web', array $permissions = []): Role
    {
        $role = Role::create([
            'name' => $name,
            'guard_name' => $guardName,
            'scope_type' => $this->getMorphClass(),
            'scope_id' => $this->getKey(),
            'is_global' => false,
        ]);

        if (!empty($permissions)) {
            $role->givePermissionTo($permissions);
        }

        return $role;
    }

    /**
     * Get all users with permissions in this scope
     */
    public function getUsersWithPermissions(): Collection
    {
        $scopeType = $this->getMorphClass();
        $scopeId = $this->getKey();
        
        return app(config('auth.providers.users.model'))::query()
            ->whereHas('permissions', function ($query) use ($scopeType, $scopeId) {
                $query->inheritable($scopeType, $scopeId);
            })
            ->orWhereHas('roles.permissions', function ($query) use ($scopeType, $scopeId) {
                $query->inheritable($scopeType, $scopeId);
            })
            ->get();
    }

    /**
     * Get all users with specific permission in this scope
     */
    public function getUsersWithPermission(string $permission): Collection
    {
        $scopeType = $this->getMorphClass();
        $scopeId = $this->getKey();
        
        return app(config('auth.providers.users.model'))::query()
            ->whereHas('permissions', function ($query) use ($permission, $scopeType, $scopeId) {
                $query->where('name', $permission)
                      ->inheritable($scopeType, $scopeId);
            })
            ->orWhereHas('roles', function ($query) use ($permission, $scopeType, $scopeId) {
                $query->inheritable($scopeType, $scopeId)
                      ->whereHas('permissions', function ($q) use ($permission) {
                          $q->where('name', $permission);
                      });
            })
            ->get();
    }

    /**
     * Get all users with specific role in this scope
     */
    public function getUsersWithRole(string $role): Collection
    {
        $scopeType = $this->getMorphClass();
        $scopeId = $this->getKey();
        
        return app(config('auth.providers.users.model'))::query()
            ->whereHas('roles', function ($query) use ($role, $scopeType, $scopeId) {
                $query->where('name', $role)
                      ->inheritable($scopeType, $scopeId);
            })
            ->get();
    }

    /**
     * Check if resource inherits permissions from parent scopes
     */
    public function inheritsPermissions(): bool
    {
        $scope = PermissionScope::where('scope_type', $this->getMorphClass())
            ->where('scope_id', $this->getKey())
            ->first();
            
        return $scope ? $scope->inherits_permissions : false;
    }

    /**
     * Enable permission inheritance for this resource
     */
    public function enablePermissionInheritance(): void
    {
        $scope = $this->getOrCreatePermissionScope();
        $scope->update(['inherits_permissions' => true]);
        
        // Clear permission cache for all users
        $this->clearPermissionCache();
    }

    /**
     * Disable permission inheritance for this resource
     */
    public function disablePermissionInheritance(): void
    {
        $scope = $this->getOrCreatePermissionScope();
        $scope->update(['inherits_permissions' => false]);
        
        // Clear permission cache for all users
        $this->clearPermissionCache();
    }

    /**
     * Get the parent scope for this resource
     */
    public function getParentScope(): ?PermissionScope
    {
        $scope = PermissionScope::where('scope_type', $this->getMorphClass())
            ->where('scope_id', $this->getKey())
            ->first();
            
        if ($scope && $scope->parent_scope_type && $scope->parent_scope_id) {
            return PermissionScope::where('scope_type', $scope->parent_scope_type)
                ->where('scope_id', $scope->parent_scope_id)
                ->first();
        }
        
        return null;
    }

    /**
     * Get all child scopes for this resource
     */
    public function getChildScopes(): Collection
    {
        return PermissionScope::where('parent_scope_type', $this->getMorphClass())
            ->where('parent_scope_id', $this->getKey())
            ->get();
    }

    /**
     * Set up common permissions for this resource type
     */
    public function setupDefaultPermissions(array $permissions = []): void
    {
        if (empty($permissions)) {
            $permissions = $this->getDefaultPermissions();
        }

        $scope = $this->getOrCreatePermissionScope();
        
        foreach ($permissions as $permission) {
            $this->createScopedPermission($permission);
        }
    }

    /**
     * Get default permissions for this resource type
     * Override this method in your models to define default permissions
     */
    protected function getDefaultPermissions(): array
    {
        $resourceName = strtolower(class_basename($this));
        
        return [
            "view_{$resourceName}",
            "create_{$resourceName}",
            "edit_{$resourceName}",
            "delete_{$resourceName}",
            "manage_{$resourceName}",
        ];
    }

    /**
     * Clear permission cache for this scope
     */
    protected function clearPermissionCache(): void
    {
        $users = $this->getUsersWithPermissions();
        $scopeType = $this->getMorphClass();
        $scopeId = $this->getKey();
        
        foreach ($users as $user) {
            cache()->forget("user_permissions_{$user->getKey()}_{$scopeType}_{$scopeId}");
            cache()->forget("user_roles_{$user->getKey()}_{$scopeType}_{$scopeId}");
        }
        
        // Also clear Spatie's main permission cache
        app()['cache']
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }
}