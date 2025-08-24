<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\ActivityLogService;
use App\Services\SessionManagementService;
use App\Services\TrustedDeviceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function __construct(
        private SessionManagementService $sessionService,
        private TrustedDeviceService $trustedDeviceService
    ) {}

    /**
     * Show the login page.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('auth/login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = Auth::user();

        // Check if this is a trusted device
        $trustedDevice = $this->trustedDeviceService->checkTrustedDevice($request, $user);

        // Create session record
        $this->sessionService->createSession($request, $user, $trustedDevice);

        // Log successful login
        ActivityLogService::logAuth('login', 'User logged in successfully', [
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'has_mfa' => $user->hasMfaEnabled(),
            'trusted_device' => $trustedDevice ? [
                'id' => $trustedDevice->id,
                'device_name' => $trustedDevice->device_name,
            ] : null,
        ], $user);

        // Always redirect to intended route
        // If user has MFA enabled, the mfa.verified middleware will catch it and show challenge
        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = Auth::user();

        // Log logout before actually logging out
        if ($user) {
            // Terminate session record
            $this->sessionService->terminateSession(session()->getId(), $user);

            ActivityLogService::logAuth('logout', 'User logged out', [
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ], $user);
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Clear MFA verification
        $request->session()->forget('mfa_verified');

        return redirect('/');
    }
}
