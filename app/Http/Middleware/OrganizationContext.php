<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OrganizationContext
{
    /**
     * Handle an incoming request.
     *
     * This middleware sets the team context for Spatie Permission
     * when working with organization-specific routes.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get organization from route parameter
        $organization = $request->route('organization');
        
        if ($organization instanceof Organization) {
            // Set the team context for permissions
            setPermissionsTeamId($organization->id);
            
            // Add organization to request for easy access
            $request->merge(['current_organization' => $organization]);
        }

        $response = $next($request);

        // Clear team context after request
        if ($organization instanceof Organization) {
            setPermissionsTeamId(null);
        }

        return $response;
    }
}
