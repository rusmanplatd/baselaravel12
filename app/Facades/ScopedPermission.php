<?php

namespace App\Facades;

use App\Services\ScopedPermissionService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static void grantPermissionToUser(\App\Models\User $user, string $permission, string $scopeType, string $scopeId, ?string $teamId = null)
 * @method static void revokePermissionFromUser(\App\Models\User $user, string $permission, string $scopeType, string $scopeId)
 * @method static void assignRoleToUser(\App\Models\User $user, string $role, string $scopeType, string $scopeId, ?string $teamId = null)
 * @method static void removeRoleFromUser(\App\Models\User $user, string $role, string $scopeType, string $scopeId)
 * @method static \App\Models\Auth\PermissionScope setupScopeHierarchy(\Illuminate\Database\Eloquent\Model $resource, ?string $parentScopeType = null, ?string $parentScopeId = null, bool $inheritsPermissions = true, array $metadata = [])
 * @method static \Illuminate\Support\Collection getUserEffectivePermissions(\App\Models\User $user, string $scopeType, string $scopeId)
 * @method static \Illuminate\Database\Eloquent\Collection getUsersWithPermissionInScope(string $permission, string $scopeType, string $scopeId)
 * @method static void bulkAssignPermissions(\Illuminate\Database\Eloquent\Collection $users, array $permissions, string $scopeType, string $scopeId, ?string $teamId = null)
 * @method static void bulkRevokePermissions(\Illuminate\Database\Eloquent\Collection $users, array $permissions, string $scopeType, string $scopeId)
 * @method static void clonePermissions(string $fromScopeType, string $fromScopeId, string $toScopeType, string $toScopeId)
 * @method static array getPermissionInheritanceTree(string $scopeType, string $scopeId)
 *
 * @see \App\Services\ScopedPermissionService
 */
class ScopedPermission extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ScopedPermissionService::class;
    }
}