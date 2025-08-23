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
}