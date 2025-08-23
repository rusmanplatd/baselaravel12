<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\ActivityLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
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

        // Log successful login
        ActivityLogService::logAuth('login', 'User logged in successfully', [
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'has_mfa' => $user->hasMfaEnabled(),
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
