<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Spatie\Permission\Traits\HasRoles as BaseHasRoles;
use Spatie\Permission\Contracts\Role;
use Spatie\Permission\Contracts\Permission;

trait HasScopedRoles
{
    use BaseHasRoles {
        BaseHasRoles::roles as baseRoles;
        BaseHasRoles::permissions as basePermissions;
    }

    /**
     * Roles with scope awareness.
     */
    public function roles($scope = null): MorphToMany
    {
        $query = $this->baseRoles();

        if ($scope) {
            $query->wherePivot('scope_type', get_class($scope))
                ->wherePivot('scope_id', $scope->id);
        } else {
            $query->wherePivotNull('scope_type')
                ->wherePivotNull('scope_id');
        }

        return $query;
    }

    /**
     * Permissions with scope awareness.
     */
    public function permissions($scope = null): MorphToMany
    {
        $query = $this->basePermissions();

        if ($scope) {
            $query->wherePivot('scope_type', get_class($scope))
                ->wherePivot('scope_id', $scope->id);
        } else {
            $query->wherePivotNull('scope_type')
                ->wherePivotNull('scope_id');
        }

        return $query;
    }

    /**
     * Assign a role, optionally scoped.
     */
    public function assignRole($roles, $scope = null)
    {
        $roles = is_array($roles) ? $roles : [$roles];

        foreach ($roles as $role) {
            $role = $this->getStoredRole($role);

            $this->roles()->attach($role->id, [
                'scope_type' => $scope ? get_class($scope) : null,
                'scope_id'   => $scope ? $scope->id : null,
            ]);
        }

        return $this;
    }

    /**
     * Check if user has role in optional scope.
     */
    public function hasRole($roles, $scope = null): bool
    {
        $roles = is_array($roles) ? $roles : [$roles];

        return $this->roles($scope)
            ->whereIn('name', $roles)
            ->exists();
    }

    /**
     * Give permission with optional scope.
     */
    public function givePermissionTo($permissions, $scope = null)
    {
        $permissions = is_array($permissions) ? $permissions : [$permissions];

        foreach ($permissions as $permission) {
            $permission = $this->getStoredPermission($permission);

            $this->permissions()->attach($permission->id, [
                'scope_type' => $scope ? get_class($scope) : null,
                'scope_id'   => $scope ? $scope->id : null,
            ]);
        }

        return $this;
    }

    /**
     * Check permission in optional scope.
     */
    public function hasPermissionTo($permission, $scope = null): bool
    {
        return $this->permissions($scope)
            ->where('name', $permission)
            ->exists();
    }
}
