<?php

namespace App\Http\Middleware;

use App\Services\TrustedDeviceService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class TrustedDeviceMiddleware
{
    public function __construct(
        private TrustedDeviceService $trustedDeviceService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $mode = 'optional'): Response
    {
        $user = Auth::user();

        if (! $user) {
            return $next($request);
        }

        $trustedDevice = $this->trustedDeviceService->checkTrustedDevice($request, $user);

        // Share trusted device status with frontend
        Inertia::share([
            'trustedDevice' => $trustedDevice ? [
                'id' => $trustedDevice->id,
                'device_name' => $trustedDevice->device_name,
                'device_type' => $trustedDevice->device_type,
                'last_used_at' => $trustedDevice->last_used_at,
                'is_current' => true,
            ] : null,
        ]);

        // Store trusted device in request for controllers
        $request->attributes->set('trusted_device', $trustedDevice);

        if ($mode === 'required' && ! $trustedDevice) {
            // Redirect to trust device page
            return redirect()->route('security.trust-device');
        }

        // Update last used if trusted device exists
        if ($trustedDevice) {
            $trustedDevice->updateLastUsed();
        }

        return $next($request);
    }
}
