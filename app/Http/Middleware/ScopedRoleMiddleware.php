<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Spatie\Permission\Exceptions\UnauthorizedException;

class ScopedRoleMiddleware
{
    public function handle(Request $request, Closure $next, string $role, string $scopeType = null, string $scopeParam = null, string $guard = null)
    {
        $authGuard = app('auth')->guard($guard);

        if ($authGuard->guest()) {
            throw UnauthorizedException::notLoggedIn();
        }

        $user = $authGuard->user();

        // If no scope specified, check global role
        if (!$scopeType || !$scopeParam) {
            if (!$user->hasRole($role)) {
                throw UnauthorizedException::forRoles([$role]);
            }
            return $next($request);
        }

        // Get scope ID from request parameter
        $scopeId = $request->route($scopeParam) ?? $request->input($scopeParam);
        
        if (!$scopeId) {
            throw UnauthorizedException::forRoles([$role]);
        }

        // Check scoped role
        if (!$user->hasRoleInScope($role, $scopeType, $scopeId)) {
            throw UnauthorizedException::forRoles([$role]);
        }

        return $next($request);
    }
}