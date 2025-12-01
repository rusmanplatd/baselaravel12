<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\SecurityAuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function __construct(
        private SecurityAuditService $securityAuditService
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
        try {
            $request->authenticate();

            $request->session()->regenerate();

            // Log successful login
            $this->securityAuditService->logEvent(
                'auth.login.success',
                Auth::user(),
                null,
                null,
                [
                    'login_method' => 'password',
                    'remember' => $request->boolean('remember'),
                ],
                $request,
                Auth::user()->currentOrganization?->id
            );

            return redirect()->intended(route('dashboard', absolute: false));

        } catch (\Illuminate\Validation\ValidationException $e) {
            // Log failed login attempt
            $this->securityAuditService->logEvent(
                'auth.login.failed',
                null,
                null,
                null,
                [
                    'email' => $request->input('email'),
                    'reason' => 'invalid_credentials',
                    'errors' => $e->errors(),
                ],
                $request
            );

            throw $e;
        }
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = Auth::user();

        // Log logout before actually logging out
        if ($user) {
            $this->securityAuditService->logEvent(
                'auth.logout',
                $user,
                null,
                null,
                [
                    'logout_method' => 'manual',
                ],
                $request,
                $user->currentOrganization?->id
            );
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
