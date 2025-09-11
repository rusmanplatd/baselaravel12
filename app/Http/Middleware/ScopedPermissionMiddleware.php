<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Spatie\Permission\Exceptions\UnauthorizedException;

class ScopedPermissionMiddleware
{
    public function handle(Request $request, Closure $next, string $permission, string $scopeType = null, string $scopeParam = null, string $guard = null)
    {
        $authGuard = app('auth')->guard($guard);

        if ($authGuard->guest()) {
            throw UnauthorizedException::notLoggedIn();
        }

        $user = $authGuard->user();

        // If no scope specified, check global permission
        if (!$scopeType || !$scopeParam) {
            if (!$user->can($permission)) {
                throw UnauthorizedException::forPermissions([$permission]);
            }
            return $next($request);
        }

        // Get scope ID from request parameter
        $scopeId = $request->route($scopeParam) ?? $request->input($scopeParam);
        
        if (!$scopeId) {
            throw UnauthorizedException::forPermissions([$permission]);
        }

        // Check scoped permission
        if (!$user->hasPermissionInScope($permission, $scopeType, $scopeId)) {
            throw UnauthorizedException::forPermissions([$permission]);
        }

        return $next($request);
    }
}