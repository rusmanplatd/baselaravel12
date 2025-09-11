<?php

namespace App\Services;

use App\Models\Auth\Permission;
use App\Models\Auth\PermissionScope;
use App\Models\Auth\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection as SupportCollection;

class ScopedPermissionService
{
    /**
     * Grant permission to user within a specific scope
     */
    public function grantPermissionToUser(
        User $user,
        string $permission,
        string $scopeType,
        string $scopeId,
        ?string $teamId = null
    ): void {
        $permissionModel = Permission::findByName($permission);
        
        if (!$permissionModel) {
            // Create scoped permission if it doesn't exist
            $permissionModel = Permission::create([
                'name' => $permission,
                'guard_name' => 'web',
                'scope_type' => $scopeType,
                'scope_id' => $scopeId,
            ]);
        }

        $user->permissions()->syncWithoutDetaching([
            $permissionModel->id => [
                'scope_type' => $scopeType,
                'scope_id' => $scopeId,
                'scope_path' => $this->buildScopePath($scopeType, $scopeId),
                'team_id' => $teamId ?? $this->getTeamIdForScope($scopeType, $scopeId),
            ]
        ]);

        $this->clearUserPermissionCache($user);
    }

    /**
     * Revoke permission from user within a specific scope
     */
    public function revokePermissionFromUser(
        User $user,
        string $permission,
        string $scopeType,
        string $scopeId
    ): void {
        $permissionModel = Permission::findByName($permission);
        
        if (!$permissionModel) {
            return;
        }

        $user->permissions()
            ->wherePivot('permission_id', $permissionModel->id)
            ->wherePivot('scope_type', $scopeType)
            ->wherePivot('scope_id', $scopeId)
            ->detach();

        $this->clearUserPermissionCache($user);
    }

    /**
     * Assign role to user within a specific scope
     */
    public function assignRoleToUser(
        User $user,
        string $role,
        string $scopeType,
        string $scopeId,
        ?string $teamId = null
    ): void {
        $roleModel = Role::findByName($role);
        
        if (!$roleModel) {
            // Create scoped role if it doesn't exist
            $roleModel = Role::create([
                'name' => $role,
                'guard_name' => 'web',
                'scope_type' => $scopeType,
                'scope_id' => $scopeId,
                'team_id' => $teamId ?? $this->getTeamIdForScope($scopeType, $scopeId),
            ]);
        }

        $user->roles()->syncWithoutDetaching([
            $roleModel->id => [
                'scope_type' => $scopeType,
                'scope_id' => $scopeId,
                'scope_path' => $this->buildScopePath($scopeType, $scopeId),
                'team_id' => $teamId ?? $this->getTeamIdForScope($scopeType, $scopeId),
            ]
        ]);

        $this->clearUserPermissionCache($user);
    }

    /**
     * Remove role from user within a specific scope
     */
    public function removeRoleFromUser(
        User $user,
        string $role,
        string $scopeType,
        string $scopeId
    ): void {
        $roleModel = Role::findByName($role);
        
        if (!$roleModel) {
            return;
        }

        $user->roles()
            ->wherePivot('role_id', $roleModel->id)
            ->wherePivot('scope_type', $scopeType)
            ->wherePivot('scope_id', $scopeId)
            ->detach();

        $this->clearUserPermissionCache($user);
    }

    /**
     * Set up permission scope hierarchy for a resource
     */
    public function setupScopeHierarchy(
        Model $resource,
        ?string $parentScopeType = null,
        ?string $parentScopeId = null,
        bool $inheritsPermissions = true,
        array $metadata = []
    ): PermissionScope {
        return PermissionScope::updateOrCreate(
            [
                'scope_type' => $resource->getMorphClass(),
                'scope_id' => $resource->getKey(),
            ],
            [
                'parent_scope_type' => $parentScopeType,
                'parent_scope_id' => $parentScopeId,
                'inherits_permissions' => $inheritsPermissions,
                'metadata' => $metadata,
                'scope_path' => $this->buildScopePath(
                    $resource->getMorphClass(),
                    $resource->getKey(),
                    $parentScopeType,
                    $parentScopeId
                ),
            ]
        );
    }

    /**
     * Get effective permissions for user in a specific scope
     */
    public function getUserEffectivePermissions(
        User $user,
        string $scopeType,
        string $scopeId
    ): SupportCollection {
        // Get direct permissions
        $directPermissions = $user->permissions()
            ->inheritable($scopeType, $scopeId)
            ->get()
            ->map(function ($permission) {
                return [
                    'name' => $permission->name,
                    'source' => 'direct',
                    'scope_type' => $permission->pivot->scope_type,
                    'scope_id' => $permission->pivot->scope_id,
                ];
            });

        // Get permissions through roles
        $rolePermissions = collect();
        $userRoles = $user->getRolesForScope($scopeType, $scopeId);
        
        foreach ($userRoles as $role) {
            foreach ($role->permissions as $permission) {
                $rolePermissions->push([
                    'name' => $permission->name,
                    'source' => 'role',
                    'role' => $role->name,
                    'scope_type' => $role->pivot->scope_type ?? $permission->scope_type,
                    'scope_id' => $role->pivot->scope_id ?? $permission->scope_id,
                ]);
            }
        }

        return $directPermissions->merge($rolePermissions)->unique('name');
    }

    /**
     * Get all users with specific permission in scope
     */
    public function getUsersWithPermissionInScope(
        string $permission,
        string $scopeType,
        string $scopeId
    ): Collection {
        return User::query()
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
     * Bulk assign permissions to multiple users in scope
     */
    public function bulkAssignPermissions(
        Collection $users,
        array $permissions,
        string $scopeType,
        string $scopeId,
        ?string $teamId = null
    ): void {
        foreach ($users as $user) {
            foreach ($permissions as $permission) {
                $this->grantPermissionToUser($user, $permission, $scopeType, $scopeId, $teamId);
            }
        }
    }

    /**
     * Bulk revoke permissions from multiple users in scope
     */
    public function bulkRevokePermissions(
        Collection $users,
        array $permissions,
        string $scopeType,
        string $scopeId
    ): void {
        foreach ($users as $user) {
            foreach ($permissions as $permission) {
                $this->revokePermissionFromUser($user, $permission, $scopeType, $scopeId);
            }
        }
    }

    /**
     * Clone permissions from one scope to another
     */
    public function clonePermissions(
        string $fromScopeType,
        string $fromScopeId,
        string $toScopeType,
        string $toScopeId
    ): void {
        // Clone direct permissions
        $directPermissions = User::query()
            ->whereHas('permissions', function ($query) use ($fromScopeType, $fromScopeId) {
                $query->where('scope_type', $fromScopeType)
                      ->where('scope_id', $fromScopeId);
            })
            ->with(['permissions' => function ($query) use ($fromScopeType, $fromScopeId) {
                $query->where('scope_type', $fromScopeType)
                      ->where('scope_id', $fromScopeId);
            }])
            ->get();

        foreach ($directPermissions as $user) {
            foreach ($user->permissions as $permission) {
                $this->grantPermissionToUser(
                    $user,
                    $permission->name,
                    $toScopeType,
                    $toScopeId
                );
            }
        }

        // Clone role assignments
        $roleAssignments = User::query()
            ->whereHas('roles', function ($query) use ($fromScopeType, $fromScopeId) {
                $query->where('scope_type', $fromScopeType)
                      ->where('scope_id', $fromScopeId);
            })
            ->with(['roles' => function ($query) use ($fromScopeType, $fromScopeId) {
                $query->where('scope_type', $fromScopeType)
                      ->where('scope_id', $fromScopeId);
            }])
            ->get();

        foreach ($roleAssignments as $user) {
            foreach ($user->roles as $role) {
                $this->assignRoleToUser(
                    $user,
                    $role->name,
                    $toScopeType,
                    $toScopeId
                );
            }
        }
    }

    /**
     * Get permission inheritance tree for a scope
     */
    public function getPermissionInheritanceTree(
        string $scopeType,
        string $scopeId
    ): array {
        $scope = PermissionScope::where('scope_type', $scopeType)
            ->where('scope_id', $scopeId)
            ->first();

        if (!$scope) {
            return [];
        }

        $tree = [];
        
        if ($scope->scope_path) {
            foreach ($scope->scope_path as $pathItem) {
                $parentScope = PermissionScope::where('scope_type', $pathItem['type'])
                    ->where('scope_id', $pathItem['id'])
                    ->first();

                if ($parentScope) {
                    $tree[] = [
                        'scope_type' => $pathItem['type'],
                        'scope_id' => $pathItem['id'],
                        'inherits_permissions' => $parentScope->inherits_permissions,
                        'metadata' => $parentScope->metadata,
                    ];
                }
            }
        }

        return $tree;
    }

    /**
     * Build scope path array
     */
    protected function buildScopePath(
        string $scopeType,
        string $scopeId,
        ?string $parentScopeType = null,
        ?string $parentScopeId = null
    ): ?array {
        if (!$parentScopeType || !$parentScopeId) {
            return null;
        }

        $parentScope = PermissionScope::where('scope_type', $parentScopeType)
            ->where('scope_id', $parentScopeId)
            ->first();

        $path = [];
        
        if ($parentScope && $parentScope->scope_path) {
            $path = $parentScope->scope_path;
        }

        $path[] = [
            'type' => $scopeType,
            'id' => $scopeId
        ];

        return $path;
    }

    /**
     * Get team ID for scope
     */
    protected function getTeamIdForScope(string $scopeType, string $scopeId): ?string
    {
        if ($scopeType === 'organization') {
            return $scopeId;
        }

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
     * Clear user permission cache
     */
    protected function clearUserPermissionCache(User $user): void
    {
        // Clear specific cache keys that might be affected
        $cacheKeys = [
            "user_permissions_{$user->getKey()}_*",
            "user_roles_{$user->getKey()}_*",
        ];

        foreach ($cacheKeys as $pattern) {
            cache()->forget($pattern);
        }

        // Clear Spatie's main permission cache
        app()['cache']
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }
}