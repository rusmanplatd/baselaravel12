<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Project;

class ProjectPermissionMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Get project from route parameter
        $projectId = $request->route('project') ?? $request->route('project_id');
        
        if (!$projectId) {
            return response()->json(['error' => 'Project ID not found'], 400);
        }

        $project = Project::find($projectId);
        
        if (!$project) {
            return response()->json(['error' => 'Project not found'], 404);
        }

        // Check if user has the required permission for this project
        if (!$user->hasPermissionTo($permission, $project)) {
            return response()->json([
                'error' => 'Forbidden', 
                'message' => "You don't have permission to {$permission} on this project"
            ], 403);
        }

        // Add project to request for use in controller
        $request->attributes->set('project', $project);

        return $next($request);
    }
}