<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chat\Conversation;
use App\Models\UserDevice;
use App\Services\MultiDeviceEncryptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DeviceController extends Controller
{
    public function __construct(
        private MultiDeviceEncryptionService $multiDeviceService
    ) {
        $this->middleware('auth:api');
    }

    public function index(Request $request)
    {
        $currentDeviceFingerprint = $request->header('X-Device-Fingerprint');

        $devices = auth()->user()
            ->devices()
            ->active()
            ->orderBy('last_used_at', 'desc')
            ->get()
            ->map(function (UserDevice $device) use ($currentDeviceFingerprint) {
                return [
                    'id' => $device->id,
                    'device_name' => $device->device_name,
                    'device_type' => $device->device_type,
                    'platform' => $device->platform,
                    'short_fingerprint' => $device->short_fingerprint,
                    'is_trusted' => $device->is_trusted,
                    'last_used_at' => $device->last_used_at,
                    'created_at' => $device->created_at,
                    'is_current' => $currentDeviceFingerprint === $device->device_fingerprint,
                ];
            });

        return response()->json([
            'devices' => $devices,
            'total' => $devices->count(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'device_name' => 'required|string|max:255',
            'device_type' => 'required|string|in:mobile,desktop,web,tablet',
            'public_key' => 'required|string',
            'device_fingerprint' => 'required|string|max:255',
            'hardware_fingerprint' => 'nullable|string|max:255',
            'platform' => 'nullable|string|max:100',
            'user_agent' => 'nullable|string|max:500',
            'device_capabilities' => 'nullable|array',
            'device_capabilities.*' => 'string|in:messaging,encryption,passkey,biometric,file_sharing,video_call,offline',
            'security_level' => 'nullable|string|in:low,medium,high,maximum',
            'device_info' => 'nullable|array',
        ]);

        try {
            $device = $this->multiDeviceService->registerDevice(
                auth()->user(),
                $validated['device_name'],
                $validated['device_type'],
                $validated['public_key'],
                $validated['device_fingerprint'],
                $validated['platform'] ?? null,
                $validated['user_agent'] ?? null,
                $validated['device_capabilities'] ?? ['messaging', 'encryption'],
                $validated['security_level'] ?? 'medium',
                $validated['device_info'] ?? []
            );

            // Initiate device verification for new devices
            $verification = null;
            if (! $device->is_trusted) {
                $verification = $this->multiDeviceService->initiateDeviceVerification($device, [
                    'type' => $validated['verification_type'] ?? 'security_key',
                ]);
            }

            return response()->json([
                'message' => 'Device registered successfully',
                'device' => [
                    'id' => $device->id,
                    'device_name' => $device->device_name,
                    'device_type' => $device->device_type,
                    'device_fingerprint' => $device->device_fingerprint,
                    'security_level' => $device->security_level,
                    'security_score' => $device->getSecurityScore(),
                    'is_trusted' => $device->is_trusted,
                    'requires_verification' => ! $device->is_trusted,
                    'device_capabilities' => $device->device_capabilities,
                ],
                'verification' => $verification,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to register device',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(UserDevice $device)
    {
        $this->authorize('view', $device);

        $summary = $this->multiDeviceService->getDeviceEncryptionSummary($device);

        return response()->json([
            'device' => array_merge($summary, [
                'device_fingerprint' => $device->device_fingerprint,
                'platform' => $device->platform,
                'user_agent' => $device->user_agent,
                'verified_at' => $device->verified_at,
                'created_at' => $device->created_at,
            ]),
        ]);
    }

    public function update(Request $request, UserDevice $device)
    {
        $this->authorize('update', $device);

        $validated = $request->validate([
            'device_name' => 'sometimes|required|string|max:255',
            'platform' => 'sometimes|nullable|string|max:100',
        ]);

        $device->update($validated);
        $device->updateLastUsed();

        return response()->json([
            'message' => 'Device updated successfully',
            'device' => [
                'id' => $device->id,
                'device_name' => $device->device_name,
                'platform' => $device->platform,
                'last_used_at' => $device->last_used_at,
            ],
        ]);
    }

    public function destroy(UserDevice $device)
    {
        $this->authorize('delete', $device);

        try {
            // Revoke access to all conversations
            $this->multiDeviceService->revokeDeviceAccess($device);

            // Deactivate the device
            $device->deactivate();

            return response()->json([
                'message' => 'Device removed successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to remove device',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function trust(Request $request, UserDevice $device)
    {
        $this->authorize('update', $device);

        $validated = $request->validate([
            'verification_code' => 'sometimes|string', // Optional verification step
        ]);

        try {
            $device->markAsTrusted();

            return response()->json([
                'message' => 'Device marked as trusted',
                'device' => [
                    'id' => $device->id,
                    'is_trusted' => $device->is_trusted,
                    'verified_at' => $device->verified_at,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to trust device',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function shareKeys(Request $request, UserDevice $device)
    {
        $this->authorize('update', $device);

        $validated = $request->validate([
            'from_device_fingerprint' => 'required|string',
        ]);

        try {
            $user = auth()->user();
            $fromDevice = $user->getDeviceByFingerprint($validated['from_device_fingerprint']);

            if (! $fromDevice) {
                return response()->json([
                    'error' => 'Source device not found',
                ], 404);
            }

            if (! $fromDevice->is_trusted) {
                return response()->json([
                    'error' => 'Source device is not trusted',
                ], 403);
            }

            $results = $this->multiDeviceService->shareKeysWithNewDevice($fromDevice, $device);

            return response()->json([
                'message' => 'Key sharing initiated',
                'shared_conversations' => $results['shared_conversations'],
                'total_keys_shared' => $results['total_keys_shared'],
                'failed_conversations' => $results['failed_conversations'],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to share keys',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function getKeyShares(UserDevice $device)
    {
        $this->authorize('view', $device);

        try {
            $keyShares = $this->multiDeviceService->getDeviceKeyShares($device);

            return response()->json([
                'key_shares' => $keyShares->map(function ($share) {
                    return [
                        'id' => $share->id,
                        'conversation_id' => $share->conversation_id,
                        'conversation_name' => $share->conversation->name ?? 'Direct Chat',
                        'from_device_name' => $share->fromDevice->display_name,
                        'from_device_fingerprint' => $share->fromDevice->short_fingerprint,
                        'share_method' => $share->share_method,
                        'expires_at' => $share->expires_at,
                        'created_at' => $share->created_at,
                    ];
                }),
                'total' => $keyShares->count(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to get key shares',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function verifyDevice(Request $request, UserDevice $device)
    {
        $this->authorize('update', $device);

        $validated = $request->validate([
            'challenge_id' => 'required|string',
            'response' => 'required|array',
            'response.signature' => 'required_if:response.type,security_key|string',
            'response.code' => 'required_if:response.type,verification_code|string',
        ]);

        try {
            $verified = $this->multiDeviceService->completeDeviceVerification(
                $device,
                $validated['challenge_id'],
                $validated['response']
            );

            if ($verified) {
                return response()->json([
                    'message' => 'Device verified successfully',
                    'device' => [
                        'id' => $device->id,
                        'is_trusted' => $device->fresh()->is_trusted,
                        'verified_at' => $device->fresh()->verified_at,
                        'security_score' => $device->fresh()->getSecurityScore(),
                    ],
                    'success' => true,
                ]);
            } else {
                return response()->json([
                    'error' => 'Device verification failed',
                    'success' => false,
                ], 422);
            }

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Device verification error',
                'message' => $e->getMessage(),
                'success' => false,
            ], 500);
        }
    }

    public function completeVerification(Request $request, UserDevice $device)
    {
        return $this->verifyDevice($request, $device);
    }

    public function initiateVerification(Request $request, UserDevice $device)
    {
        $this->authorize('update', $device);

        $validated = $request->validate([
            'verification_type' => 'nullable|string|in:security_key,verification_code,passkey,biometric',
        ]);

        try {
            $verification = $this->multiDeviceService->initiateDeviceVerification(
                $device,
                $validated
            );

            return response()->json([
                'message' => 'Device verification initiated',
                'verification' => $verification,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to initiate verification',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function getSecurityReport(UserDevice $device)
    {
        $this->authorize('view', $device);

        try {
            $integrityReport = $this->multiDeviceService->verifyDeviceIntegrity($device);
            $encryptionSummary = $this->multiDeviceService->getDeviceEncryptionSummary($device);

            return response()->json([
                'device_id' => $device->id,
                'device_name' => $device->display_name,
                'integrity_report' => $integrityReport,
                'encryption_summary' => $encryptionSummary,
                'generated_at' => now(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to generate security report',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function rotateKeys(Request $request, UserDevice $device)
    {
        $this->authorize('update', $device);

        $validated = $request->validate([
            'conversation_ids' => 'nullable|array',
            'conversation_ids.*' => 'string|exists:chat_conversations,id',
        ]);

        try {
            // If specific conversations are provided, rotate keys for those
            // Otherwise, rotate keys for all conversations this device has access to
            if (isset($validated['conversation_ids'])) {
                $results = [];
                foreach ($validated['conversation_ids'] as $conversationId) {
                    $conversation = Conversation::findOrFail($conversationId);
                    $result = $this->multiDeviceService->rotateConversationKeys($conversation, $device);
                    $results[$conversationId] = $result;
                }
            } else {
                // Rotate all keys for this device
                $conversations = $device->encryptionKeys()
                    ->active()
                    ->with('conversation')
                    ->get()
                    ->pluck('conversation')
                    ->unique('id');

                $results = [];
                foreach ($conversations as $conversation) {
                    $result = $this->multiDeviceService->rotateConversationKeys($conversation, $device);
                    $results[$conversation->id] = $result;
                }
            }

            // Update device's last key rotation timestamp
            $device->update(['last_key_rotation_at' => now()]);

            return response()->json([
                'message' => 'Key rotation completed',
                'rotated_conversations' => array_keys($results),
                'results' => $results,
                'total_rotated' => count($results),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to rotate keys',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function unlockDevice(Request $request, UserDevice $device)
    {
        $this->authorize('update', $device);

        // This method would typically require admin privileges
        if (! auth()->user()->can('admin.device.unlock')) {
            return response()->json([
                'error' => 'Insufficient permissions to unlock device',
            ], 403);
        }

        try {
            $device->update([
                'locked_until' => null,
                'failed_auth_attempts' => 0,
            ]);

            return response()->json([
                'message' => 'Device unlocked successfully',
                'device' => [
                    'id' => $device->id,
                    'is_locked' => false,
                    'failed_attempts' => 0,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to unlock device',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sync a message to this device
     */
    public function syncMessage(Request $request, UserDevice $device)
    {
        $this->authorize('update', $device);

        $validated = $request->validate([
            'message_id' => 'required|string',
            'conversation_id' => 'required|string|exists:chat_conversations,id',
            'encrypted_content' => 'required|array',
            'source_device_id' => 'required|string|exists:user_devices,id',
            'timestamp' => 'required|integer',
        ]);

        try {
            // Store the synced message in device's message cache
            $cacheKey = "device_sync_{$device->id}_{$validated['conversation_id']}";
            $messages = cache()->get($cacheKey, []);

            $messages[] = [
                'message_id' => $validated['message_id'],
                'encrypted_content' => $validated['encrypted_content'],
                'source_device_id' => $validated['source_device_id'],
                'timestamp' => $validated['timestamp'],
                'synced_at' => now(),
            ];

            // Keep only last 100 messages in cache
            if (count($messages) > 100) {
                $messages = array_slice($messages, -100);
            }

            cache()->put($cacheKey, $messages, now()->addDays(7));

            return response()->json([
                'message' => 'Message synced successfully',
                'sync_timestamp' => now(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to sync message',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Request messages from this device
     */
    public function requestMessages(Request $request, UserDevice $device)
    {
        $this->authorize('view', $device);

        $validated = $request->validate([
            'conversation_id' => 'required|string|exists:chat_conversations,id',
            'from_timestamp' => 'required|integer',
            'requesting_device_id' => 'required|string|exists:user_devices,id',
        ]);

        try {
            // Get messages from device's cache
            $cacheKey = "device_sync_{$device->id}_{$validated['conversation_id']}";
            $messages = cache()->get($cacheKey, []);

            // Filter messages by timestamp
            $requestedMessages = array_filter($messages, function ($message) use ($validated) {
                return $message['timestamp'] >= $validated['from_timestamp'];
            });

            return response()->json([
                'messages' => array_values($requestedMessages),
                'total_count' => count($requestedMessages),
                'device_id' => $device->id,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to request messages',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate QR code for device verification
     */
    public function generateVerificationQRCode(UserDevice $device)
    {
        $this->authorize('update', $device);

        try {
            $verificationData = [
                'device_id' => $device->id,
                'verification_code' => Str::random(32),
                'expires_at' => now()->addMinutes(5),
            ];

            // Store verification code temporarily
            cache()->put("device_verification_qr_{$device->id}", $verificationData, now()->addMinutes(5));

            $verificationUrl = url("/verify-device/{$device->id}/".$verificationData['verification_code']);

            // Generate QR code data
            $qrCodeData = base64_encode(json_encode([
                'type' => 'device_verification',
                'url' => $verificationUrl,
                'device_id' => $device->id,
                'expires' => $verificationData['expires_at']->timestamp,
            ]));

            return response()->json([
                'qr_code' => $qrCodeData,
                'verification_url' => $verificationUrl,
                'expires_at' => $verificationData['expires_at'],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to generate QR code',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
