<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetPermissionTeamContext
{
    /**
     * Handle an incoming request.
     *
     * This middleware sets the default team context for Spatie Permission
     * to work with team-based permissions.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip setting team context for global reference data routes
        if ($this->shouldSkipTeamContext($request)) {
            return $next($request);
        }

        // Set default team context if teams are enabled
        if (config('permission.teams', false)) {
            // Get the default organization for team context
            $defaultOrg = Organization::where('organization_code', 'DEFAULT')->first();

            if ($defaultOrg) {
                // Set the team context for permissions
                setPermissionsTeamId($defaultOrg->id);
            }
        }

        $response = $next($request);

        // Clear team context after request if teams are enabled
        if (config('permission.teams', false)) {
            setPermissionsTeamId(null);
        }

        return $response;
    }

    /**
     * Determine if team context should be skipped for this request.
     * Global reference data like geographical data should not have team context.
     */
    private function shouldSkipTeamContext(Request $request): bool
    {
        $path = $request->path();

        // Skip team context for geographical routes (global reference data)
        $globalRoutes = [
            'geography/',
            'api/v1/geo/',
        ];

        foreach ($globalRoutes as $globalRoute) {
            if (str_starts_with($path, $globalRoute)) {
                return true;
            }
        }

        return false;
    }
}
