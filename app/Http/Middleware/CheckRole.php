<?php

namespace App\Http\Middleware;

use App\Services\ActivityLogService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();

        // Check if user has any of the required roles
        if (! $user->hasAnyRole($roles)) {
            // Log role access denied
            ActivityLogService::logAuth('role_access_denied', 'User attempted to access resource without required role', [
                'required_roles' => $roles,
                'user_roles' => $user->getRoleNames()->toArray(),
                'requested_url' => $request->fullUrl(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Forbidden',
                    'message' => 'You do not have the required role to access this resource.',
                ], 403);
            }

            abort(403, 'You do not have the required role to access this resource.');
        }

        return $next($request);
    }
}
