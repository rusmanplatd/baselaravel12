<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelPasskeys\Http\Requests\StorePasskeyRequest;
use Spatie\LaravelPasskeys\Models\Passkey;

class WebAuthnController extends Controller
{
    public function registerOptions(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        $options = $user->passkeyRegistrationOptions();

        return response()->json($options);
    }

    public function register(StorePasskeyRequest $request): JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        try {
            $passkey = $user->storePasskey(
                $request->safe()->merge(['name' => $request->input('name', 'Security Key')])
            );

            // Log passkey registration
            ActivityLogService::logAuth('passkey_registered', 'New passkey registered', [
                'passkey_name' => $passkey->name,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ], $user);

            return response()->json([
                'success' => true,
                'passkey' => [
                    'id' => $passkey->id,
                    'name' => $passkey->name,
                    'created_at' => $passkey->created_at,
                ],
            ]);
        } catch (\Exception $e) {
            // Log failed passkey registration
            ActivityLogService::logAuth('passkey_registration_failed', 'Failed to register passkey', [
                'error' => $e->getMessage(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ], $user);

            return response()->json(['error' => 'Failed to register passkey'], 400);
        }
    }

    public function authenticateOptions(Request $request): JsonResponse
    {
        $options = Passkey::passkeyAuthenticationOptions();

        return response()->json($options);
    }

    public function authenticate(Request $request): JsonResponse
    {
        try {
            $user = User::passkeyAuthentication($request->all());

            if (! $user) {
                throw ValidationException::withMessages([
                    'webauthn' => ['WebAuthn authentication failed.'],
                ]);
            }

            Auth::login($user, $request->boolean('remember'));

            $request->session()->regenerate();

            // If user has TOTP MFA enabled, they still need to verify it
            // WebAuthn is a separate form of authentication, not a replacement for TOTP MFA
            $requiresMfaChallenge = $user->hasMfaEnabled();

            // Log successful passkey authentication
            ActivityLogService::logAuth('passkey_login', 'User logged in with passkey', [
                'requires_mfa' => $requiresMfaChallenge,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ], $user);

            return response()->json([
                'success' => true,
                'requires_mfa' => $requiresMfaChallenge,
                'redirect_url' => $requiresMfaChallenge ? null : route('dashboard'),
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ]);
        } catch (\Exception $e) {
            // Log failed passkey authentication
            ActivityLogService::logAuth('passkey_login_failed', 'Failed passkey authentication', [
                'error' => $e->getMessage(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json(['error' => 'Authentication failed'], 400);
        }
    }

    public function delete(Request $request, Passkey $passkey): JsonResponse
    {
        $user = Auth::user();

        if (! $user || $passkey->passable_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Log passkey deletion
        ActivityLogService::logAuth('passkey_deleted', 'Passkey deleted', [
            'passkey_id' => $passkey->id,
            'passkey_name' => $passkey->name,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ], $user);

        $passkey->delete();

        return response()->json(['success' => true]);
    }

    public function list(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        $passkeys = $user->passkeys()->select(['id', 'name', 'created_at'])->get();

        return response()->json(['passkeys' => $passkeys]);
    }
}
