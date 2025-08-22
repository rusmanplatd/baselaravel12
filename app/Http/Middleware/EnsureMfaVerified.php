<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class EnsureMfaVerified
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->route('login');
        }

        // Skip MFA verification for certain routes
        $exemptRoutes = [
            'mfa.setup',
            'mfa.enable',
            'mfa.confirm',
            'mfa.disable',
            'mfa.verify',
            'mfa.backup-codes.regenerate',
            'logout',
        ];

        if (in_array($request->route()->getName(), $exemptRoutes)) {
            return $next($request);
        }

        // If user has MFA enabled but hasn't verified in this session
        if ($user->hasMfaEnabled() && ! $request->session()->get('mfa_verified')) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'MFA verification required'], 423);
            }

            return Inertia::render('Auth/MfaChallenge');
        }

        return $next($request);
    }
}
