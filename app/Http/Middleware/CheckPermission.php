<?php

namespace App\Http\Middleware;

use App\Services\ActivityLogService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission, ?string $organizationContext = null): Response
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();

        // Super admin bypass - check for super admin roles with team context
        $defaultOrg = \App\Models\Organization::where('organization_code', 'DEFAULT')->first();
        
        $isSuperAdmin = false;
        if ($defaultOrg) {
            // Check with team context
            $isSuperAdmin = $user->hasRole('Super Admin', $defaultOrg->id) || 
                           $user->hasRole('super-admin', $defaultOrg->id);
        }
        
        // Also check without team context as fallback
        if (!$isSuperAdmin) {
            $isSuperAdmin = $user->hasRole('super-admin') || $user->hasRole('Super Admin');
        }
        
        // Check by direct database query as final fallback
        if (!$isSuperAdmin) {
            $superAdminAssignments = \Illuminate\Support\Facades\DB::table('sys_model_has_roles')
                ->join('sys_roles', 'sys_model_has_roles.role_id', '=', 'sys_roles.id')
                ->where('sys_model_has_roles.model_type', 'App\Models\User')
                ->where('sys_model_has_roles.model_id', $user->id)
                ->whereIn('sys_roles.name', ['Super Admin', 'super-admin'])
                ->exists();
            
            $isSuperAdmin = $superAdminAssignments;
        }
        
        if ($isSuperAdmin) {
            return $next($request);
        }

        // Check if user has the required permission
        // First try with team context if available
        $hasPermission = false;
        
        if ($defaultOrg) {
            // Set team context and check permission
            $previousTeam = getPermissionsTeamId();
            setPermissionsTeamId($defaultOrg->id);
            $hasPermission = $user->can($permission);
            setPermissionsTeamId($previousTeam);
        }
        
        // Fallback to non-team permission check
        if (!$hasPermission) {
            $hasPermission = $user->can($permission);
        }
        
        // Final fallback: check if user has permission through role assignments
        if (!$hasPermission) {
            $hasPermissionViaRole = \Illuminate\Support\Facades\DB::table('sys_model_has_roles')
                ->join('sys_roles', 'sys_model_has_roles.role_id', '=', 'sys_roles.id')
                ->join('sys_role_has_permissions', 'sys_roles.id', '=', 'sys_role_has_permissions.role_id')
                ->join('sys_permissions', 'sys_role_has_permissions.permission_id', '=', 'sys_permissions.id')
                ->where('sys_model_has_roles.model_type', 'App\Models\User')
                ->where('sys_model_has_roles.model_id', $user->id)
                ->where('sys_permissions.name', $permission)
                ->exists();
            
            $hasPermission = $hasPermissionViaRole;
        }
        
        if (! $hasPermission) {
            // Log permission denied
            ActivityLogService::logAuth('permission_denied', 'User attempted to access resource without permission', [
                'required_permission' => $permission,
                'user_permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
                'requested_url' => $request->fullUrl(),
                'organization_context' => $organizationContext,
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Forbidden',
                    'message' => 'You do not have permission to perform this action.',
                ], 403);
            }

            abort(403, 'You do not have permission to perform this action.');
        }

        // Additional organization-specific checks if context is provided
        if ($organizationContext && $request->route($organizationContext)) {
            $organizationId = $request->route($organizationContext);

            // Check if user has access to the specific organization
            $userOrganizations = $user->activeOrganizationMemberships()->pluck('organization_id');

            if (! $userOrganizations->contains($organizationId)) {
                // Log organization access denied
                ActivityLogService::logAuth('organization_access_denied', 'User attempted to access organization without membership', [
                    'required_permission' => $permission,
                    'organization_id' => $organizationId,
                    'user_organizations' => $userOrganizations->toArray(),
                ]);

                if ($request->expectsJson()) {
                    return response()->json([
                        'error' => 'Forbidden',
                        'message' => 'You do not have access to this organization.',
                    ], 403);
                }

                abort(403, 'You do not have access to this organization.');
            }
        }

        return $next($request);
    }
}
