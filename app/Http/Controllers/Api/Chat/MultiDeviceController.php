<?php

namespace App\Http\Controllers\Api\Chat;

use App\Http\Controllers\Controller;
use App\Services\MultiDeviceQuantumE2EEService;
use App\Models\UserDevice;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class MultiDeviceController extends Controller
{
    private MultiDeviceQuantumE2EEService $multiDeviceService;

    public function __construct(MultiDeviceQuantumE2EEService $multiDeviceService)
    {
        $this->multiDeviceService = $multiDeviceService;
        $this->middleware('auth');
    }

    /**
     * Initialize multi-device support for the current user.
     */
    public function initialize(Request $request): JsonResponse
    {
        $user = Auth::user();
        $result = $this->multiDeviceService->initializeMultiDevice($user);

        if (!$result['success']) {
            return response()->json([
                'error' => $result['error'],
            ], 500);
        }

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Get all trusted devices for the current user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        $devices = $user->devices()
            ->with('session')
            ->orderBy('last_seen_at', 'desc')
            ->get()
            ->map(function ($device) {
                return [
                    'device_id' => $device->device_id,
                    'device_name' => $device->device_name,
                    'device_type' => $device->device_type,
                    'device_type_display' => $device->device_type_display,
                    'platform' => $device->platform,
                    'is_current_device' => $device->is_current_device,
                    'is_trusted' => $device->is_trusted,
                    'trust_level' => $device->trust_level,
                    'verification_status' => $device->verification_status,
                    'verification_status_display' => $device->verification_status_display,
                    'quantum_security_level' => $device->quantum_security_level,
                    'is_online' => $device->isOnline(),
                    'last_seen_at' => $device->last_seen_at?->toISOString(),
                    'verified_at' => $device->verified_at?->toISOString(),
                    'last_key_rotation_at' => $device->last_key_rotation_at?->toISOString(),
                    'created_at' => $device->created_at->toISOString(),
                    'can_be_revoked' => $device->canBeRevoked(),
                    'verification_code' => $device->getVerificationCode(),
                ];
            });

        return response()->json([
            'success' => true,
            'devices' => $devices,
        ]);
    }

    /**
     * Register a new device.
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'device_name' => 'required|string|max:255',
            'device_type' => ['required', Rule::in(['desktop', 'mobile', 'tablet', 'web'])],
            'platform' => 'nullable|string|max:100',
            'public_key' => 'required|array',
            'quantum_key_info' => 'required|array',
            'quantum_security_level' => 'nullable|integer|min:0|max:10',
            'device_fingerprint' => 'nullable|array',
            'security_metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors(),
            ], 422);
        }

        $user = Auth::user();
        $result = $this->multiDeviceService->registerDevice($user, $request->all());

        if (!$result['success']) {
            return response()->json([
                'error' => $result['error'],
            ], 400);
        }

        return response()->json([
            'success' => true,
            'device_id' => $result['device_id'],
            'verification_code' => $result['verification_code'],
            'expires_at' => $result['expires_at'],
        ], 201);
    }

    /**
     * Verify a device with verification code.
     */
    public function verify(Request $request, string $deviceId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'verification_code' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors(),
            ], 422);
        }

        $user = Auth::user();
        $result = $this->multiDeviceService->verifyDevice(
            $user, 
            $deviceId, 
            $request->input('verification_code')
        );

        if (!$result['success']) {
            return response()->json([
                'error' => $result['error'],
            ], 400);
        }

        return response()->json([
            'success' => true,
            'device' => $result['device'],
            'session_id' => $result['session_id'],
        ]);
    }

    /**
     * Revoke a device.
     */
    public function revoke(Request $request, string $deviceId): JsonResponse
    {
        $user = Auth::user();
        $result = $this->multiDeviceService->revokeDevice($user, $deviceId);

        if (!$result['success']) {
            return response()->json([
                'error' => $result['error'],
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
        ]);
    }

    /**
     * Sync device keys.
     */
    public function syncKeys(Request $request, ?string $deviceId = null): JsonResponse
    {
        $user = Auth::user();
        $result = $this->multiDeviceService->syncDeviceKeys($user, $deviceId);

        if (!$result['success']) {
            return response()->json([
                'error' => $result['error'],
            ], 400);
        }

        return response()->json([
            'success' => true,
            'synced_devices' => $result['synced_devices'],
            'total_devices' => $result['total_devices'],
            'sync_results' => $result['sync_results'],
        ]);
    }

    /**
     * Rotate device keys.
     */
    public function rotateKeys(Request $request, ?string $deviceId = null): JsonResponse
    {
        $user = Auth::user();
        $result = $this->multiDeviceService->rotateDeviceKeys($user, $deviceId);

        if (!$result['success']) {
            return response()->json([
                'error' => $result['error'],
            ], 400);
        }

        return response()->json([
            'success' => true,
            'rotated_devices' => $result['rotated_devices'],
            'rotation_results' => $result['rotation_results'],
        ]);
    }

    /**
     * Update device information.
     */
    public function update(Request $request, string $deviceId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'device_name' => 'sometimes|string|max:255',
            'trust_level' => 'sometimes|integer|min:0|max:10',
            'quantum_security_level' => 'sometimes|integer|min:0|max:10',
            'public_key' => 'sometimes|array',
            'quantum_key_info' => 'sometimes|array',
            'security_metadata' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors(),
            ], 422);
        }

        $user = Auth::user();
        $device = $user->devices()->where('device_id', $deviceId)->first();

        if (!$device) {
            return response()->json([
                'error' => 'Device not found',
            ], 404);
        }

        $updateData = $request->only([
            'device_name',
            'trust_level',
            'quantum_security_level',
            'public_key',
            'quantum_key_info',
            'security_metadata',
        ]);

        $device->update(array_filter($updateData));
        $device->updateLastSeen();

        return response()->json([
            'success' => true,
            'device' => [
                'device_id' => $device->device_id,
                'device_name' => $device->device_name,
                'device_type' => $device->device_type,
                'platform' => $device->platform,
                'is_current_device' => $device->is_current_device,
                'is_trusted' => $device->is_trusted,
                'trust_level' => $device->trust_level,
                'verification_status' => $device->verification_status,
                'quantum_security_level' => $device->quantum_security_level,
                'last_seen_at' => $device->last_seen_at?->toISOString(),
                'updated_at' => $device->updated_at->toISOString(),
            ],
        ]);
    }

    /**
     * Get security metrics for multi-device setup.
     */
    public function metrics(Request $request): JsonResponse
    {
        $user = Auth::user();
        $metrics = $this->multiDeviceService->getMultiDeviceSecurityMetrics($user);

        return response()->json([
            'success' => true,
            'metrics' => $metrics,
        ]);
    }

    /**
     * Export security audit report.
     */
    public function audit(Request $request): JsonResponse
    {
        $user = Auth::user();
        $audit = $this->multiDeviceService->exportMultiDeviceAudit($user);

        return response()->json([
            'success' => true,
            'audit' => $audit,
        ]);
    }

    /**
     * Send encrypted message to multiple devices.
     */
    public function sendMessage(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'conversation_id' => 'required|integer|exists:chat_conversations,id',
            'sender_device_id' => 'required|string|exists:user_devices,device_id',
            'message_content' => 'required|string',
            'encrypted_data' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors(),
            ], 422);
        }

        $user = Auth::user();
        $result = $this->multiDeviceService->encryptForMultipleDevices(
            $user,
            $request->input('sender_device_id'),
            $request->input('conversation_id'),
            $request->input('message_content'),
            $request->input('encrypted_data')
        );

        if (!$result['success']) {
            return response()->json([
                'error' => $result['error'],
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message_id' => $result['message_id'],
            'target_device_count' => $result['target_device_count'],
        ], 201);
    }

    /**
     * Get encrypted message for decryption.
     */
    public function getMessage(Request $request, string $messageId): JsonResponse
    {
        $deviceId = $request->input('device_id');
        
        if (!$deviceId) {
            return response()->json([
                'error' => 'Device ID is required',
            ], 400);
        }

        // Verify user owns this device
        $user = Auth::user();
        $device = $user->devices()
            ->where('device_id', $deviceId)
            ->trusted()
            ->first();

        if (!$device) {
            return response()->json([
                'error' => 'Device not found or not trusted',
            ], 403);
        }

        $message = $this->multiDeviceService->getCrossDeviceMessage($messageId, $deviceId);

        if (!$message) {
            return response()->json([
                'error' => 'Message not found or not available for this device',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => $message,
        ]);
    }

    /**
     * Update device heartbeat.
     */
    public function heartbeat(Request $request, string $deviceId): JsonResponse
    {
        $user = Auth::user();
        $device = $user->devices()->where('device_id', $deviceId)->first();

        if (!$device) {
            return response()->json([
                'error' => 'Device not found',
            ], 404);
        }

        $device->updateLastSeen();

        return response()->json([
            'success' => true,
            'last_seen_at' => $device->last_seen_at->toISOString(),
        ]);
    }

    /**
     * Get pending verification devices.
     */
    public function pendingVerification(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        $pendingDevices = $user->devices()
            ->where('verification_status', 'pending')
            ->where('verification_expires_at', '>', now())
            ->get()
            ->map(function ($device) {
                return [
                    'device_id' => $device->device_id,
                    'device_name' => $device->device_name,
                    'device_type' => $device->device_type,
                    'platform' => $device->platform,
                    'verification_code' => $device->getVerificationCode(),
                    'expires_at' => $device->verification_expires_at?->toISOString(),
                    'created_at' => $device->created_at->toISOString(),
                ];
            });

        return response()->json([
            'success' => true,
            'pending_devices' => $pendingDevices,
        ]);
    }
}