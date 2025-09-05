<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Services\ActivityLogService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckOrganizationAccess
{
    public function handle(Request $request, Closure $next, string $parameterName = 'organization'): Response
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();

        // Super admin bypass
        if ($user->hasRole('super-admin')) {
            return $next($request);
        }

        $organizationId = $request->route($parameterName);

        if (! $organizationId) {
            // No organization context provided
            return $next($request);
        }

        // Check if organization exists
        $organization = Organization::find($organizationId);
        if (! $organization) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Not Found',
                    'message' => 'Organization not found.',
                ], 404);
            }
            abort(404, 'Organization not found.');
        }

        // Check if user has access to the organization
        $userOrganizations = $user->activeOrganizationMemberships()->pluck('organization_id');

        if (! $userOrganizations->contains($organizationId)) {
            // Log organization access denied
            ActivityLogService::logOrganization('access_denied', 'User attempted to access organization without membership', [
                'organization_id' => $organizationId,
                'organization_name' => $organization->name,
                'user_organizations' => $userOrganizations->toArray(),
                'requested_url' => $request->fullUrl(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Forbidden',
                    'message' => 'You do not have access to this organization.',
                ], 403);
            }

            abort(403, 'You do not have access to this organization.');
        }

        // Add organization to request for easy access in controllers
        $request->merge(['current_organization' => $organization]);

        return $next($request);
    }
}
