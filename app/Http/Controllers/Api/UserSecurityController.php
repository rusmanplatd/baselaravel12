<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RevokeSessionsRequest;
use App\Http\Requests\Api\UpdatePasswordRequest;
use App\Http\Requests\Api\UpdateSecuritySettingsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response as ScribeResponse;

#[Group('User Security')]
class UserSecurityController extends Controller
{
    #[Endpoint(
        title: 'Get security profile',
        description: 'Get a comprehensive security profile for the authenticated user including authentication methods, security score, and recommendations'
    )]
    #[Authenticated]
    #[ScribeResponse([
        'user' => ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com'],
        'authentication_methods' => [
            'password' => ['enabled' => true],
            'mfa' => ['enabled' => true, 'totp_enabled' => true],
            'webauthn' => ['enabled' => true, 'passkey_count' => 2],
        ],
        'security_score' => 85,
        'recommendations' => [],
    ])]
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $mfaSettings = $user->mfaSettings;
        $passkeys = $user->passkeys();

        $securityProfile = [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ],
            'authentication_methods' => [
                'password' => [
                    'enabled' => ! empty($user->password),
                    'last_changed' => null, // Would require additional tracking
                ],
                'mfa' => [
                    'enabled' => $mfaSettings?->hasMfaEnabled() ?? false,
                    'totp_enabled' => $mfaSettings?->totp_enabled ?? false,
                    'confirmed_at' => $mfaSettings?->totp_confirmed_at,
                    'backup_codes' => [
                        'total' => count($mfaSettings?->backup_codes ?? []),
                        'used' => $mfaSettings?->backup_codes_used ?? 0,
                        'remaining' => max(0, count($mfaSettings?->backup_codes ?? []) - ($mfaSettings?->backup_codes_used ?? 0)),
                    ],
                ],
                'webauthn' => [
                    'enabled' => $passkeys->exists(),
                    'passkey_count' => $passkeys->count(),
                    'most_recent_registration' => $passkeys->max('created_at'),
                    'most_recent_use' => $passkeys->max('last_used_at'),
                ],
            ],
            'security_score' => $this->calculateSecurityScore($user, $mfaSettings, $passkeys),
            'recommendations' => $this->getSecurityRecommendations($user, $mfaSettings, $passkeys),
            'last_updated' => now()->toISOString(),
        ];

        return response()->json($securityProfile);
    }

    /**
     * Get security activity and audit log
     */
    public function activity(Request $request): JsonResponse
    {
        $user = $request->user();

        // This would typically integrate with an audit log system
        $activities = [
            [
                'type' => 'profile_viewed',
                'timestamp' => now()->subMinutes(1)->toISOString(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
            ],
            // Additional activities would come from an audit log
        ];

        return response()->json([
            'activities' => $activities,
            'pagination' => [
                'current_page' => 1,
                'per_page' => 50,
                'total' => count($activities),
            ],
        ]);
    }

    #[Endpoint(
        title: 'Update password',
        description: "Update the user's password with current password verification"
    )]
    #[Authenticated]
    #[ScribeResponse(['message' => 'Password updated successfully', 'updated_at' => '2024-01-15T10:30:00Z'], 200)]
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = $request->user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The provided password is incorrect.'],
            ]);
        }

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        return response()->json([
            'message' => 'Password updated successfully',
            'updated_at' => $user->updated_at,
        ]);
    }

    /**
     * Get account security recommendations
     */
    public function recommendations(Request $request): JsonResponse
    {
        $user = $request->user();
        $mfaSettings = $user->mfaSettings;
        $passkeys = $user->passkeys();

        $recommendations = $this->getSecurityRecommendations($user, $mfaSettings, $passkeys);

        return response()->json([
            'recommendations' => $recommendations,
            'security_score' => $this->calculateSecurityScore($user, $mfaSettings, $passkeys),
        ]);
    }

    /**
     * Get security settings summary
     */
    public function settings(Request $request): JsonResponse
    {
        $user = $request->user();
        $mfaSettings = $user->mfaSettings;

        return response()->json([
            'mfa_enabled' => $mfaSettings?->hasMfaEnabled() ?? false,
            'mfa_required' => $mfaSettings?->mfa_required ?? false,
            'webauthn_enabled' => $user->passkeys()->exists(),
            'email_verified' => ! is_null($user->email_verified_at),
            'password_set' => ! empty($user->password),
            'account_locked' => false, // Would depend on your account locking implementation
            'login_attempts' => 0, // Would require additional tracking
        ]);
    }

    /**
     * Update security settings
     */
    public function updateSettings(UpdateSecuritySettingsRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = $request->user();

        if (! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['The provided password is incorrect.'],
            ]);
        }

        if (isset($validated['mfa_required'])) {
            if (! $user->hasMfaEnabled() && $validated['mfa_required']) {
                return response()->json([
                    'error' => 'Cannot require MFA when MFA is not enabled',
                    'code' => 'MFA_NOT_ENABLED',
                ], 400);
            }

            $user->mfaSettings()->updateOrCreate(
                ['user_id' => $user->id],
                ['mfa_required' => $validated['mfa_required']]
            );
        }

        return response()->json([
            'message' => 'Security settings updated successfully',
            'settings' => [
                'mfa_required' => $user->mfaSettings?->mfa_required ?? false,
            ],
        ]);
    }

    /**
     * Get current active sessions
     */
    public function sessions(Request $request): JsonResponse
    {
        // This would typically integrate with a session tracking system
        $currentSession = [
            'id' => session()->getId(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
            'created_at' => now()->toISOString(),
            'last_activity' => now()->toISOString(),
            'is_current' => true,
        ];

        return response()->json([
            'sessions' => [$currentSession],
            'current_session_id' => $currentSession['id'],
        ]);
    }

    /**
     * Revoke all sessions except current
     */
    public function revokeSessions(RevokeSessionsRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = $request->user();

        if (! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['The provided password is incorrect.'],
            ]);
        }

        // Revoke all API tokens except current
        $user->tokens()->where('id', '!=', $request->user()->currentAccessToken()?->id)->delete();

        return response()->json([
            'message' => 'All other sessions have been revoked',
        ]);
    }

    /**
     * Calculate security score based on enabled features
     */
    private function calculateSecurityScore($user, $mfaSettings, $passkeysQuery): int
    {
        $score = 0;

        // Base score for having an account
        $score += 10;

        // Email verification
        if ($user->email_verified_at) {
            $score += 15;
        }

        // Password strength (simplified)
        if (! empty($user->password)) {
            $score += 20;
        }

        // MFA enabled
        if ($mfaSettings?->hasMfaEnabled()) {
            $score += 30;
        }

        // WebAuthn/Passkeys
        if ($passkeysQuery->exists()) {
            $score += 25;
        }

        // Multiple passkeys
        if ($passkeysQuery->count() > 1) {
            $score += 10;
        }

        return min(100, $score);
    }

    /**
     * Get personalized security recommendations
     */
    private function getSecurityRecommendations($user, $mfaSettings, $passkeysQuery): array
    {
        $recommendations = [];

        if (! $user->email_verified_at) {
            $recommendations[] = [
                'type' => 'email_verification',
                'priority' => 'high',
                'title' => 'Verify your email address',
                'description' => 'Email verification helps secure your account and enables password reset.',
                'action' => 'Verify email',
            ];
        }

        if (! $mfaSettings?->hasMfaEnabled()) {
            $recommendations[] = [
                'type' => 'enable_mfa',
                'priority' => 'high',
                'title' => 'Enable two-factor authentication',
                'description' => 'Add an extra layer of security with time-based one-time passwords.',
                'action' => 'Set up MFA',
            ];
        }

        if (! $passkeysQuery->exists()) {
            $recommendations[] = [
                'type' => 'add_passkey',
                'priority' => 'medium',
                'title' => 'Add a security key',
                'description' => 'Security keys provide phishing-resistant authentication.',
                'action' => 'Add passkey',
            ];
        }

        if ($mfaSettings?->backup_codes && count($mfaSettings->backup_codes) - $mfaSettings->backup_codes_used < 3) {
            $recommendations[] = [
                'type' => 'regenerate_backup_codes',
                'priority' => 'medium',
                'title' => 'Regenerate backup codes',
                'description' => 'You have few backup codes remaining. Generate new ones for account recovery.',
                'action' => 'Regenerate codes',
            ];
        }

        if ($passkeysQuery->count() === 1) {
            $recommendations[] = [
                'type' => 'multiple_passkeys',
                'priority' => 'low',
                'title' => 'Add a backup security key',
                'description' => 'Multiple security keys ensure you can still access your account if one is lost.',
                'action' => 'Add another passkey',
            ];
        }

        return $recommendations;
    }
}
