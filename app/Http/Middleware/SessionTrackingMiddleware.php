<?php

namespace App\Http\Middleware;

use App\Services\SessionManagementService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SessionTrackingMiddleware
{
    public function __construct(
        private SessionManagementService $sessionService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user) {
            return $next($request);
        }

        // Get trusted device from previous middleware
        $trustedDevice = $request->attributes->get('trusted_device');

        // Update session activity
        $this->sessionService->updateSessionActivity(
            session()->getId(),
            $trustedDevice
        );

        return $next($request);
    }
}
