<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class BroadcastingAuth
{
    /**
     * Handle an incoming request.
     * Authenticate using API tokens for broadcasting endpoints (no session support).
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Try to authenticate via API token (no session support for broadcasting)
        $token = $this->extractToken($request);

        if ($token) {
            $user = $this->authenticateWithToken($token, $request);

            if ($user) {
                // Set the authenticated user for this request
                Auth::setUser($user);
                Log::info('Broadcasting authenticated via API token', [
                    'user_id' => $user->id,
                    'endpoint' => $request->path(),
                ]);

                return $next($request);
            }
        }

        // No valid authentication found
        Log::warning('Broadcasting authentication failed', [
            'endpoint' => $request->path(),
            'has_token' => ! empty($token),
            'method' => $request->method(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'message' => 'Unauthorized. Please provide a valid API token for broadcasting authentication.',
            'error' => 'missing_or_invalid_token',
        ], 401);
    }

    /**
     * Extract token from request
     */
    private function extractToken(Request $request): ?string
    {
        // Check Authorization header first
        $authHeader = $request->header('Authorization');
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            return substr($authHeader, 7);
        }

        // Check token parameter (for query string or form data)
        return $request->input('token');
    }

    /**
     * Authenticate user with API token
     */
    private function authenticateWithToken(string $token, Request $request): ?\App\Models\User
    {
        try {
            // Create a temporary request with the auth header
            $originalRequest = app('request');

            // Create new request for token validation
            $testRequest = Request::create($request->getUri(), $request->getMethod(), [], [], [], [], '');
            $testRequest->headers->set('Authorization', 'Bearer '.$token);

            // Copy other relevant headers
            $testRequest->headers->add($request->headers->all());
            $testRequest->headers->set('Authorization', 'Bearer '.$token); // Ensure auth header is set

            // Temporarily swap the request
            app()->instance('request', $testRequest);

            // Use the API guard to authenticate
            $guard = auth('api');

            // Clear any cached user if method exists
            if (method_exists($guard, 'forgetUser')) {
                $guard->forgetUser();
            }

            $user = $guard->user();

            // Restore original request
            app()->instance('request', $originalRequest);

            // Clear cache again after restoring if method exists
            if (method_exists($guard, 'forgetUser')) {
                $guard->forgetUser();
            }

            return $user;
        } catch (\Exception $e) {
            Log::error('Broadcasting token authentication error', [
                'error' => $e->getMessage(),
                'token' => substr($token, 0, 10).'...',
            ]);

            return null;
        }
    }
}
