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

        // Super admin bypass
        if ($user->hasRole('super-admin')) {
            return $next($request);
        }

        // Check if user has the required permission
        if (! $user->can($permission)) {
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
