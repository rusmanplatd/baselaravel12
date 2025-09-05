<?php

namespace App\Http\Controllers\Api\Chat;

use App\Http\Controllers\Controller;
use App\Models\UserDevice;
use App\Services\SignalProtocolService;
use App\Services\SecurityAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class DeviceController extends Controller
{
    public function __construct(
        private SignalProtocolService $signalService,
        private SecurityAuditService $securityAuditService
    ) {
        $this->middleware('auth:api');
        $this->middleware('throttle:10,1')->only(['register']);
    }

    /**
     * Get user's registered devices
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $devices = UserDevice::where('user_id', $user->id)
            ->with(['signalIdentityKeys' => function ($query) {
                $query->active();
            }])
            ->orderByDesc('last_used_at')
            ->get()
            ->map(function ($device) {
                return [
                    'id' => $device->id,
                    'device_name' => $device->device_name,
                    'device_type' => $device->device_type,
                    'platform' => $device->platform,
                    'is_trusted' => $device->is_trusted,
                    'is_active' => $device->is_active,
                    'quantum_ready' => $device->quantum_ready,
                    'security_level' => $device->security_level,
                    'last_used_at' => $device->last_used_at,
                    'registered_at' => $device->created_at,
                    'encryption_capabilities' => $device->encryption_capabilities ?? [],
                    'quantum_health_score' => $device->quantum_health_score,
                    'trust_level' => $device->trust_level,
                    'fingerprint_short' => substr($device->device_fingerprint, 0, 8),
                    'signal_registration_id' => $device->signalIdentityKeys->first()?->registration_id,
                ];
            });

        return response()->json(['devices' => $devices]);
    }

    /**
     * Register a new device for E2EE
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'device_name' => 'required|string|max:255',
            'device_type' => 'required|in:mobile,desktop,web,tablet',
            'device_fingerprint' => 'required|string|max:255',
            'platform' => 'nullable|string|max:100',
            'user_agent' => 'nullable|string|max:500',
            'public_key' => 'required|string',
            'enable_quantum' => 'boolean',
            'quantum_algorithm' => 'nullable|string|in:ML-KEM-512,ML-KEM-768,ML-KEM-1024',
            'capabilities' => 'nullable|array',
            'hardware_fingerprint' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();

        // Check for existing device with same fingerprint
        $existingDevice = UserDevice::where('user_id', $user->id)
            ->where('device_fingerprint', $request->device_fingerprint)
            ->first();

        if ($existingDevice) {
            return response()->json(['error' => 'Device already registered'], 409);
        }

        try {
            DB::beginTransaction();

            // Create device record
            $device = UserDevice::create([
                'user_id' => $user->id,
                'device_name' => $request->device_name,
                'device_type' => $request->device_type,
                'device_fingerprint' => $request->device_fingerprint,
                'platform' => $request->platform,
                'user_agent' => $request->user_agent,
                'public_key' => $request->public_key,
                'hardware_fingerprint' => $request->hardware_fingerprint,
                'device_capabilities' => $request->capabilities ?? [],
                'encryption_capabilities' => $request->capabilities ?? [],
                'is_active' => true,
                'is_trusted' => false, // Requires explicit trust
                'security_level' => $this->calculateSecurityLevel($request),
                'quantum_ready' => $request->boolean('enable_quantum'),
            ]);

            // Initialize Signal Protocol for device
            $identityKey = $this->signalService->initializeDevice($user, $device, [
                'enable_quantum' => $request->boolean('enable_quantum'),
                'quantum_algorithm' => $request->input('quantum_algorithm', 'ML-KEM-768'),
            ]);

            DB::commit();

            // Log device registration for security auditing
            $this->securityAuditService->logEvent(
                'device.registered',
                $user,
                $device,
                null,
                [
                    'device_type' => $device->device_type,
                    'platform' => $device->platform,
                    'quantum_enabled' => $device->quantum_ready,
                    'security_level' => $device->security_level,
                    'capabilities' => $device->encryption_capabilities,
                    'registration_id' => $identityKey->registration_id,
                ],
                $request,
                $user->currentOrganization?->id
            );

            Log::info('New device registered', [
                'user_id' => $user->id,
                'device_id' => $device->id,
                'device_type' => $device->device_type,
                'quantum_enabled' => $device->quantum_ready,
                'registration_id' => $identityKey->registration_id,
            ]);

            return response()->json([
                'device' => [
                    'id' => $device->id,
                    'device_name' => $device->device_name,
                    'device_type' => $device->device_type,
                    'is_trusted' => $device->is_trusted,
                    'quantum_ready' => $device->quantum_ready,
                    'registration_id' => $identityKey->registration_id,
                    'fingerprint_short' => substr($device->device_fingerprint, 0, 8),
                ],
                'message' => 'Device registered successfully. Trust verification required.',
                'requires_trust' => ! $device->is_trusted,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to register device', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Failed to register device'], 500);
        }
    }

    /**
     * Trust a device
     */
    public function trust(Request $request, string $deviceId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'verification_code' => 'nullable|string|size:6',
            'auto_expire' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $device = UserDevice::where('user_id', $user->id)->findOrFail($deviceId);

        if ($device->is_trusted) {
            return response()->json(['error' => 'Device is already trusted'], 400);
        }

        // In a real implementation, you'd verify the code here
        // For now, we'll trust immediately if verification code is provided
        if ($request->verification_code) {
            // Verify the code (implementation depends on your verification method)
            $isValidCode = $this->verifyDeviceTrustCode($device, $request->verification_code);

            if (! $isValidCode) {
                return response()->json(['error' => 'Invalid verification code'], 400);
            }
        }

        try {
            $device->trust($request->boolean('auto_expire', true));

            // Log device trust for security auditing
            $this->securityAuditService->logEvent(
                'device.trusted',
                $user,
                $device,
                null,
                [
                    'verification_method' => $request->verification_code ? 'code' : 'auto',
                    'auto_expire' => $request->boolean('auto_expire'),
                    'previous_trust_level' => $device->getOriginal('trust_level'),
                    'new_trust_level' => $device->trust_level,
                ],
                $request,
                $user->currentOrganization?->id
            );

            Log::info('Device trusted', [
                'user_id' => $user->id,
                'device_id' => $device->id,
                'auto_expire' => $request->boolean('auto_expire'),
            ]);

            return response()->json([
                'device' => [
                    'id' => $device->id,
                    'is_trusted' => $device->is_trusted,
                    'trust_level' => $device->trust_level,
                    'verified_at' => $device->verified_at,
                ],
                'message' => 'Device trusted successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to trust device', [
                'user_id' => $user->id,
                'device_id' => $deviceId,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Failed to trust device'], 500);
        }
    }

    /**
     * Untrust a device
     */
    public function untrust(Request $request, string $deviceId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $device = UserDevice::where('user_id', $user->id)->findOrFail($deviceId);

        try {
            $device->untrust($request->reason);

            // Log device untrust for security auditing
            $this->securityAuditService->logEvent(
                'device.untrusted',
                $user,
                $device,
                null,
                [
                    'reason' => $request->reason,
                    'previous_trust_level' => $device->getOriginal('trust_level'),
                    'device_type' => $device->device_type,
                ],
                $request,
                $user->currentOrganization?->id
            );

            Log::info('Device untrusted', [
                'user_id' => $user->id,
                'device_id' => $device->id,
                'reason' => $request->reason,
            ]);

            return response()->json([
                'message' => 'Device untrusted successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to untrust device', [
                'user_id' => $user->id,
                'device_id' => $deviceId,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Failed to untrust device'], 500);
        }
    }

    /**
     * Revoke a device
     */
    public function revoke(Request $request, string $deviceId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $device = UserDevice::where('user_id', $user->id)->findOrFail($deviceId);

        try {
            $device->revoke($request->reason);

            // Log device revocation for security auditing
            $this->securityAuditService->logEvent(
                'device.revoked',
                $user,
                $device,
                null,
                [
                    'reason' => $request->reason,
                    'device_type' => $device->device_type,
                    'was_trusted' => $device->getOriginal('is_trusted'),
                    'had_active_sessions' => $device->getOriginal('is_active'),
                ],
                $request,
                $user->currentOrganization?->id
            );

            Log::info('Device revoked', [
                'user_id' => $user->id,
                'device_id' => $device->id,
                'reason' => $request->reason,
            ]);

            return response()->json([
                'message' => 'Device revoked successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to revoke device', [
                'user_id' => $user->id,
                'device_id' => $deviceId,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Failed to revoke device'], 500);
        }
    }

    /**
     * Get device details
     */
    public function show(Request $request, string $deviceId): JsonResponse
    {
        $user = $request->user();
        $device = UserDevice::where('user_id', $user->id)
            ->with([
                'signalIdentityKeys' => function ($query) {
                    $query->active();
                },
                'encryptionKeys' => function ($query) {
                    $query->active()->latest()->limit(5);
                },
            ])
            ->findOrFail($deviceId);

        $deviceDetails = [
            'id' => $device->id,
            'device_name' => $device->device_name,
            'device_type' => $device->device_type,
            'platform' => $device->platform,
            'is_trusted' => $device->is_trusted,
            'is_active' => $device->is_active,
            'quantum_ready' => $device->quantum_ready,
            'security_level' => $device->security_level,
            'trust_level' => $device->trust_level,
            'last_used_at' => $device->last_used_at,
            'registered_at' => $device->created_at,
            'verified_at' => $device->verified_at,
            'revoked_at' => $device->revoked_at,
            'encryption_version' => $device->encryption_version,
            'quantum_health_score' => $device->quantum_health_score,
            'capabilities' => $device->encryption_capabilities ?? [],
            'fingerprint_short' => substr($device->device_fingerprint, 0, 8),
            'security_risk' => $device->getSecurityRisk(),
            'needs_key_rotation' => $device->needsKeyRotation(),
            'signal_info' => [
                'registration_id' => $device->signalIdentityKeys->first()?->registration_id,
                'quantum_capable' => $device->signalIdentityKeys->first()?->is_quantum_capable ?? false,
                'algorithm' => $device->signalIdentityKeys->first()?->quantum_algorithm,
            ],
            'active_encryption_keys' => $device->encryptionKeys->count(),
        ];

        return response()->json(['device' => $deviceDetails]);
    }

    /**
     * Update device information
     */
    public function update(Request $request, string $deviceId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'device_name' => 'nullable|string|max:255',
            'capabilities' => 'nullable|array',
            'preferred_algorithm' => 'nullable|string|in:RSA-4096-OAEP,ML-KEM-512,ML-KEM-768,ML-KEM-1024',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $device = UserDevice::where('user_id', $user->id)->findOrFail($deviceId);

        try {
            $updates = array_filter([
                'device_name' => $request->device_name,
                'encryption_capabilities' => $request->capabilities,
                'preferred_algorithm' => $request->preferred_algorithm,
            ]);

            if (! empty($updates)) {
                $device->update($updates);
                $device->updateLastUsed();
            }

            return response()->json([
                'device' => [
                    'id' => $device->id,
                    'device_name' => $device->device_name,
                    'capabilities' => $device->encryption_capabilities,
                    'preferred_algorithm' => $device->preferred_algorithm,
                ],
                'message' => 'Device updated successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update device', [
                'user_id' => $user->id,
                'device_id' => $deviceId,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Failed to update device'], 500);
        }
    }

    /**
     * Get device verification code for trust establishment
     */
    public function getVerificationCode(Request $request, string $deviceId): JsonResponse
    {
        $user = $request->user();
        $device = UserDevice::where('user_id', $user->id)->findOrFail($deviceId);

        if ($device->is_trusted) {
            return response()->json(['error' => 'Device is already trusted'], 400);
        }

        try {
            // Generate a 6-digit verification code
            $verificationCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            // Store code temporarily (in real implementation, use cache/redis)
            cache()->put("device_verification_{$device->id}", $verificationCode, 300); // 5 minutes

            // In a real implementation, you might send this via push notification, SMS, or email

            return response()->json([
                'verification_code' => $verificationCode, // Only for development
                'expires_in' => 300,
                'message' => 'Verification code generated',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to generate verification code', [
                'user_id' => $user->id,
                'device_id' => $deviceId,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Failed to generate verification code'], 500);
        }
    }

    /**
     * Rotate device encryption keys
     */
    public function rotateKeys(Request $request, string $deviceId): JsonResponse
    {
        $user = $request->user();
        $device = UserDevice::where('user_id', $user->id)->findOrFail($deviceId);

        try {
            // Get current identity key
            $currentIdentityKey = $device->signalIdentityKeys()->active()->first();

            if (! $currentIdentityKey) {
                return response()->json(['error' => 'No active identity key found'], 400);
            }

            // Rotate the identity key
            $newIdentityKey = $this->signalService->rotateDeviceKeys($device, $currentIdentityKey);

            // Update device's key rotation timestamp
            $device->update(['last_key_rotation_at' => now()]);

            Log::info('Device keys rotated', [
                'user_id' => $user->id,
                'device_id' => $device->id,
                'old_registration_id' => $currentIdentityKey->registration_id,
                'new_registration_id' => $newIdentityKey->registration_id,
            ]);

            return response()->json([
                'new_registration_id' => $newIdentityKey->registration_id,
                'message' => 'Encryption keys rotated successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to rotate device keys', [
                'user_id' => $user->id,
                'device_id' => $deviceId,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Failed to rotate keys'], 500);
        }
    }

    /**
     * Calculate security level based on device capabilities
     */
    private function calculateSecurityLevel(Request $request): string
    {
        $score = 0;

        // Base security
        if ($request->hardware_fingerprint) {
            $score += 20;
        }

        if ($request->boolean('enable_quantum')) {
            $score += 50;
        }

        $capabilities = $request->input('capabilities', []);
        if (in_array('hardware_security', $capabilities)) {
            $score += 20;
        }

        if (in_array('biometric_auth', $capabilities)) {
            $score += 10;
        }

        if ($score >= 80) {
            return 'high';
        } elseif ($score >= 50) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    /**
     * Verify device trust code
     */
    private function verifyDeviceTrustCode(UserDevice $device, string $code): bool
    {
        $storedCode = cache()->get("device_verification_{$device->id}");

        if (! $storedCode) {
            return false;
        }

        $isValid = hash_equals($storedCode, $code);

        if ($isValid) {
            cache()->forget("device_verification_{$device->id}");
        }

        return $isValid;
    }
}
