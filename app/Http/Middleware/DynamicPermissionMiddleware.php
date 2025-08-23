<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class DynamicPermissionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $resource = null): Response
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();
        
        // If no specific resource is provided, try to infer from route
        if (! $resource) {
            $resource = $this->inferResourceFromRoute($request);
        }

        $action = $this->getActionFromMethod($request->method());
        $permission = "{$action} {$resource}";

        // Try the specific permission first
        if ($user->can($permission)) {
            return $next($request);
        }

        // Fallback to manage permission
        $managePermission = "manage {$resource}";
        if ($user->can($managePermission)) {
            return $next($request);
        }

        // If it's a view operation, check if user has any permission on the resource
        if ($action === 'view') {
            $resourcePermissions = [
                "create {$resource}",
                "edit {$resource}",
                "delete {$resource}",
            ];

            foreach ($resourcePermissions as $resourcePermission) {
                if ($user->can($resourcePermission)) {
                    return $next($request);
                }
            }
        }

        abort(403, "You don't have permission to {$permission}");
    }

    /**
     * Infer the resource name from the current route
     */
    private function inferResourceFromRoute(Request $request): string
    {
        $routeName = $request->route()->getName();
        
        if (! $routeName) {
            return 'resource';
        }

        // Extract resource from route name (e.g., 'roles.index' -> 'roles')
        $parts = explode('.', $routeName);
        
        return $parts[0] ?? 'resource';
    }

    /**
     * Map HTTP method to permission action
     */
    private function getActionFromMethod(string $method): string
    {
        return match (strtoupper($method)) {
            'GET' => 'view',
            'POST' => 'create',
            'PUT', 'PATCH' => 'edit',
            'DELETE' => 'delete',
            default => 'view',
        };
    }
}