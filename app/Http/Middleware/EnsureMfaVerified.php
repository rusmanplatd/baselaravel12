<?php

namespace App\Http\Middleware;

use App\Services\ActivityLogService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class EnsureMfaVerified
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response|InertiaResponse
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
            // Log MFA challenge required
            ActivityLogService::logAuth('mfa_challenge_required', 'User requires MFA verification', [
                'requested_url' => $request->fullUrl(),
                'route_name' => $request->route()?->getName(),
            ], $user);

            if ($request->expectsJson()) {
                return response()->json(['error' => 'MFA verification required'], 423);
            }

            return Inertia::render('Auth/MfaChallenge');
        }

        return $next($request);
    }
}
