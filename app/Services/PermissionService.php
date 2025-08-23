<?php

namespace App\Services;

use App\Models\Auth\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class PermissionService
{
    public static function getUserPermissions(?User $user = null): array
    {
        if (! $user) {
            $user = Auth::user();
        }

        if (! $user) {
            return [];
        }

        // Super admin gets all permissions
        if ($user->hasRole('super-admin')) {
            return Permission::pluck('name')->toArray();
        }

        return $user->getAllPermissions()->pluck('name')->toArray();
    }

    public static function getUserRoles(?User $user = null): array
    {
        if (! $user) {
            $user = Auth::user();
        }

        if (! $user) {
            return [];
        }

        return $user->getRoleNames()->toArray();
    }

    public static function canUser(string $permission, ?User $user = null): bool
    {
        if (! $user) {
            $user = Auth::user();
        }

        if (! $user) {
            return false;
        }

        return $user->can($permission);
    }

    public static function hasRole(string $role, ?User $user = null): bool
    {
        if (! $user) {
            $user = Auth::user();
        }

        if (! $user) {
            return false;
        }

        return $user->hasRole($role);
    }

    public static function hasAnyRole(array $roles, ?User $user = null): bool
    {
        if (! $user) {
            $user = Auth::user();
        }

        if (! $user) {
            return false;
        }

        return $user->hasAnyRole($roles);
    }

    public static function getPermissionsForFrontend(?User $user = null): array
    {
        $permissions = self::getUserPermissions($user);
        $roles = self::getUserRoles($user);

        return [
            'permissions' => $permissions,
            'roles' => $roles,
            'is_super_admin' => in_array('super-admin', $roles),
            'can' => function (string $permission) use ($permissions) {
                return in_array($permission, $permissions);
            },
            'hasRole' => function (string $role) use ($roles) {
                return in_array($role, $roles);
            },
            'hasAnyRole' => function (array $roleList) use ($roles) {
                return count(array_intersect($roleList, $roles)) > 0;
            },
        ];
    }

    public static function getOrganizationPermissions(string $organizationId, ?User $user = null): array
    {
        if (! $user) {
            $user = Auth::user();
        }

        if (! $user) {
            return [];
        }

        // Super admin gets all permissions for all organizations
        if ($user->hasRole('super-admin')) {
            return Permission::pluck('name')->toArray();
        }

        // Get user's membership for the specific organization
        $membership = $user->activeOrganizationMemberships()
            ->where('organization_id', $organizationId)
            ->first();

        if (! $membership) {
            return [];
        }

        // Get permissions based on membership type and position
        $permissions = $user->getAllPermissions()->pluck('name')->toArray();

        // Add organization-specific permissions based on position level
        if ($membership->organizationPosition) {
            $positionLevel = $membership->organizationPosition->position_level;

            switch ($positionLevel) {
                case 'c_level':
                case 'board_member':
                    // C-level and board members get almost all permissions
                    $permissions = array_merge($permissions, [
                        'organization.edit',
                        'organization.hierarchy.manage',
                        'membership.create',
                        'membership.edit',
                        'membership.delete',
                        'oauth.client.create',
                        'oauth.client.edit',
                        'oauth.analytics.view',
                    ]);
                    break;

                case 'vice_president':
                case 'director':
                    // VP and Directors get management permissions
                    $permissions = array_merge($permissions, [
                        'membership.create',
                        'membership.edit',
                        'unit.create',
                        'unit.edit',
                        'position.create',
                        'position.edit',
                    ]);
                    break;

                case 'senior_manager':
                case 'manager':
                    // Managers get limited permissions
                    $permissions = array_merge($permissions, [
                        'membership.edit',
                        'unit.edit',
                        'position.edit',
                    ]);
                    break;
            }
        }

        return array_unique($permissions);
    }

    public static function filterMenuItems(array $menuItems, ?User $user = null): array
    {
        if (! $user) {
            $user = Auth::user();
        }

        if (! $user) {
            return [];
        }

        return array_filter($menuItems, function ($item) use ($user) {
            // If no permission is specified, show the item
            if (! isset($item['permission'])) {
                return true;
            }

            // Check if user has the required permission
            return $user->can($item['permission']);
        });
    }

    public static function getManageableOrganizations(?User $user = null): array
    {
        if (! $user) {
            $user = Auth::user();
        }

        if (! $user) {
            return [];
        }

        // Super admin can manage all organizations
        if ($user->hasRole('super-admin')) {
            return \App\Models\Organization::all(['id', 'name'])->toArray();
        }

        // Get organizations where user has management-level positions
        $managementLevels = ['c_level', 'vice_president', 'director', 'senior_manager', 'manager'];

        return $user->activeOrganizationMemberships()
            ->whereHas('organizationPosition', function ($query) use ($managementLevels) {
                $query->whereIn('position_level', $managementLevels);
            })
            ->with('organization:id,name')
            ->get()
            ->pluck('organization')
            ->toArray();
    }
}
