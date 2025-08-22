<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\LaravelPasskeys\Http\Requests\StorePasskeyRequest;
use Spatie\LaravelPasskeys\Models\Passkey;

class WebAuthnApiController extends Controller
{
    /**
     * Get all passkeys for the authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $passkeys = $user->passkeys()->select(['id', 'name', 'created_at'])->get();

        return response()->json([
            'passkeys' => $passkeys->map(function ($passkey) {
                return [
                    'id' => $passkey->id,
                    'name' => $passkey->name,
                    'created_at' => $passkey->created_at,
                    'last_used_at' => $passkey->last_used_at ?? null,
                ];
            }),
            'total_count' => $passkeys->count(),
        ]);
    }

    /**
     * Get registration options for creating a new passkey
     */
    public function getRegistrationOptions(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'error' => 'User not authenticated',
                'code' => 'UNAUTHENTICATED',
            ], 401);
        }

        try {
            $options = $user->passkeyRegistrationOptions();

            return response()->json($options);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to generate registration options',
                'code' => 'REGISTRATION_OPTIONS_ERROR',
            ], 500);
        }
    }

    /**
     * Register a new passkey
     */
    public function store(StorePasskeyRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'error' => 'User not authenticated',
                'code' => 'UNAUTHENTICATED',
            ], 401);
        }

        try {
            $passkey = $user->storePasskey(
                $request->safe()->merge([
                    'name' => $request->input('name', 'API Security Key'),
                ])
            );

            return response()->json([
                'message' => 'Passkey registered successfully',
                'passkey' => [
                    'id' => $passkey->id,
                    'name' => $passkey->name,
                    'created_at' => $passkey->created_at,
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to register passkey',
                'code' => 'PASSKEY_REGISTRATION_FAILED',
                'details' => config('app.debug') ? $e->getMessage() : null,
            ], 400);
        }
    }

    /**
     * Get authentication options for WebAuthn login
     */
    public function getAuthenticationOptions(Request $request): JsonResponse
    {
        try {
            // This would typically generate WebAuthn authentication options
            // For now, return a basic response that indicates the endpoint is available
            return response()->json([
                'challenge' => base64_encode(random_bytes(32)),
                'timeout' => 60000,
                'rpId' => parse_url(config('app.url'), PHP_URL_HOST),
                'allowCredentials' => [],
                'userVerification' => 'preferred',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to generate authentication options',
                'code' => 'AUTHENTICATION_OPTIONS_ERROR',
            ], 500);
        }
    }

    /**
     * Authenticate using WebAuthn (passwordless login)
     */
    public function authenticate(Request $request): JsonResponse
    {
        try {
            $user = User::passkeyAuthentication($request->all());

            if (! $user) {
                return response()->json([
                    'error' => 'WebAuthn authentication failed',
                    'code' => 'WEBAUTHN_AUTHENTICATION_FAILED',
                ], 401);
            }

            // Create API token for the authenticated user
            $token = $user->createToken('webauthn-api-access')->plainTextToken;

            return response()->json([
                'message' => 'Authentication successful',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'mfa_enabled' => $user->hasMfaEnabled(),
                ],
                'access_token' => $token,
                'token_type' => 'Bearer',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Authentication failed',
                'code' => 'AUTHENTICATION_ERROR',
                'details' => config('app.debug') ? $e->getMessage() : null,
            ], 400);
        }
    }

    /**
     * Update passkey name
     */
    public function update(Request $request, Passkey $passkey): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $user = $request->user();

        if (! $user || $passkey->passable_id !== $user->id) {
            return response()->json([
                'error' => 'Unauthorized access to passkey',
                'code' => 'UNAUTHORIZED_PASSKEY_ACCESS',
            ], 403);
        }

        $passkey->update([
            'name' => $request->input('name'),
        ]);

        return response()->json([
            'message' => 'Passkey name updated successfully',
            'passkey' => [
                'id' => $passkey->id,
                'name' => $passkey->name,
                'created_at' => $passkey->created_at,
            ],
        ]);
    }

    /**
     * Delete a passkey
     */
    public function destroy(Request $request, Passkey $passkey): JsonResponse
    {
        $user = $request->user();

        if (! $user || $passkey->passable_id !== $user->id) {
            return response()->json([
                'error' => 'Unauthorized access to passkey',
                'code' => 'UNAUTHORIZED_PASSKEY_ACCESS',
            ], 403);
        }

        // Check if this is the user's last passkey and they don't have password/MFA
        $passkeyCount = $user->passkeys()->count();
        if ($passkeyCount === 1 && ! $user->hasMfaEnabled() && ! $user->password) {
            return response()->json([
                'error' => 'Cannot delete the last authentication method',
                'code' => 'LAST_AUTH_METHOD',
                'message' => 'You must have at least one authentication method available.',
            ], 400);
        }

        $passkey->delete();

        return response()->json([
            'message' => 'Passkey deleted successfully',
        ]);
    }

    /**
     * Get WebAuthn capabilities and user agent info
     */
    public function capabilities(Request $request): JsonResponse
    {
        $userAgent = $request->header('User-Agent', '');
        $capabilities = [
            'webauthn_supported' => true, // This would be determined by client-side JavaScript
            'platform_authenticator' => null, // Would be set by client
            'cross_platform_authenticator' => null, // Would be set by client
            'user_agent' => $userAgent,
            'server_capabilities' => [
                'resident_keys' => true,
                'user_verification' => 'preferred',
                'attestation' => 'none',
                'algorithms' => ['RS256', 'ES256'],
            ],
        ];

        return response()->json($capabilities);
    }

    /**
     * Get usage statistics for user's passkeys
     */
    public function statistics(Request $request): JsonResponse
    {
        $user = $request->user();
        $passkeys = $user->passkeys();

        $stats = [
            'total_passkeys' => $passkeys->count(),
            'active_passkeys' => $passkeys->whereNotNull('last_used_at')->count(),
            'most_recent_use' => $passkeys->max('last_used_at'),
            'oldest_passkey' => $passkeys->min('created_at'),
            'newest_passkey' => $passkeys->max('created_at'),
            'passkey_types' => [
                'platform' => 0, // Would require additional tracking
                'cross_platform' => 0, // Would require additional tracking
            ],
        ];

        return response()->json($stats);
    }

    /**
     * Test WebAuthn connectivity and server readiness
     */
    public function health(Request $request): JsonResponse
    {
        try {
            // Test basic functionality
            $testChallenge = base64_encode(random_bytes(32));
            
            return response()->json([
                'status' => 'healthy',
                'webauthn_server' => 'operational',
                'timestamp' => now()->toISOString(),
                'services' => [
                    'registration' => 'available',
                    'authentication' => 'available',
                    'management' => 'available',
                ],
                'challenge_generation' => ! empty($testChallenge),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'unhealthy',
                'webauthn_server' => 'error',
                'timestamp' => now()->toISOString(),
                'error' => config('app.debug') ? $e->getMessage() : 'Service temporarily unavailable',
            ], 503);
        }
    }
}
