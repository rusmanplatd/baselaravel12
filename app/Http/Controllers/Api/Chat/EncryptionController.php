<?php

namespace App\Http\Controllers\Api\Chat;

use App\Http\Controllers\Controller;
use App\Models\Chat\Conversation;
use App\Models\Chat\DeviceKeyShare;
use App\Models\Chat\EncryptionKey;
use App\Models\UserDevice;
use App\Services\ChatEncryptionService;
use App\Services\MultiDeviceEncryptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class EncryptionController extends Controller
{
    public function __construct(
        private ChatEncryptionService $encryptionService,
        private MultiDeviceEncryptionService $multiDeviceService
    ) {
        $this->middleware('auth');
    }

    public function generateKeyPair(Request $request)
    {
        try {
            $keyPair = $this->encryptionService->generateKeyPair();

            $cacheKey = 'user_private_key_'.auth()->id();
            Cache::put($cacheKey, $this->encryptionService->encryptForStorage($keyPair['private']), now()->addMinutes(30));

            return response()->json([
                'public_key' => $keyPair['public'],
                'key_id' => $cacheKey,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to generate key pair'], 500);
        }
    }

    public function getConversationKey(Conversation $conversation)
    {
        $this->authorize('view', $conversation);

        $encryptionKey = auth()->user()
            ->getActiveEncryptionKeyForConversation($conversation->id);

        if (! $encryptionKey) {
            return response()->json(['error' => 'No encryption key found for this conversation'], 404);
        }

        return response()->json([
            'key_id' => $encryptionKey->id,
            'encrypted_key' => $encryptionKey->encrypted_key,
            'public_key' => $encryptionKey->public_key,
        ]);
    }

    public function rotateConversationKey(Request $request, Conversation $conversation)
    {
        $this->authorize('update', $conversation);

        $validated = $request->validate([
            'reason' => 'nullable|string|max:255',
            'emergency' => 'nullable|boolean',
        ]);

        try {
            // Generate new symmetric key
            $newSymmetricKey = $this->encryptionService->generateSymmetricKey();

            // Deactivate old keys
            $conversation->encryptionKeys()->update(['is_active' => false]);

            $participants = $conversation->activeParticipants()->with('user')->get();

            // Get all participant public keys
            $participantPublicKeys = [];
            foreach ($participants as $participant) {
                if ($participant->user->public_key) {
                    $participantPublicKeys[$participant->user_id] = $participant->user->public_key;
                }
            }

            // Use bulk encryption for multiple participants
            $bulkResult = $this->encryptionService->encryptForMultipleParticipants(
                $conversation->id,
                $newSymmetricKey,
                $participantPublicKeys
            );

            // Create encryption key records for successful encryptions
            foreach ($bulkResult['encrypted_keys'] as $userId => $keyData) {
                EncryptionKey::create([
                    'conversation_id' => $conversation->id,
                    'user_id' => $userId,
                    'encrypted_key' => $keyData['encrypted_key'],
                    'public_key' => $keyData['public_key'],
                ]);
            }

            // Log the rotation event
            \Log::info('Conversation key rotated', [
                'conversation_id' => $conversation->id,
                'initiated_by' => auth()->id(),
                'reason' => $validated['reason'] ?? 'Manual rotation',
                'emergency' => $validated['emergency'] ?? false,
                'participants_count' => $participants->count(),
                'success_count' => $bulkResult['success_count'],
                'errors_count' => count($bulkResult['errors']),
            ]);

            $response = [
                'message' => 'Conversation key rotated successfully',
                'participants_updated' => $bulkResult['success_count'],
                'total_participants' => $bulkResult['total_count'],
                'rotation_id' => \Str::uuid(),
                'timestamp' => now()->toISOString(),
            ];

            if (! empty($bulkResult['errors'])) {
                $response['warnings'] = array_map(function ($userId, $error) {
                    return ['user_id' => $userId, 'error' => $error];
                }, array_keys($bulkResult['errors']), $bulkResult['errors']);
            }

            return response()->json($response);

        } catch (\Exception $e) {
            \Log::error('Failed to rotate conversation key', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Failed to rotate conversation key',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    public function scheduleKeyRotation(Request $request, Conversation $conversation)
    {
        $this->authorize('update', $conversation);

        $validated = $request->validate([
            'schedule_at' => 'required|date|after:now',
            'reason' => 'nullable|string|max:255',
            'recurring' => 'nullable|boolean',
            'interval_days' => 'nullable|integer|min:1|max:365',
        ]);

        try {
            $scheduledAt = \Carbon\Carbon::parse($validated['schedule_at']);
            $recurring = $validated['recurring'] ?? false;
            $intervalDays = $validated['interval_days'] ?? null;
            $reason = $validated['reason'] ?? 'Scheduled key rotation';

            // Dispatch the job with delay
            \App\Jobs\RotateConversationKeysJob::dispatch(
                $conversation,
                $reason,
                $recurring,
                $intervalDays
            )->delay($scheduledAt);

            // Store the scheduled rotation info for tracking
            $conversation->update([
                'metadata' => array_merge($conversation->metadata ?? [], [
                    'scheduled_key_rotation' => [
                        'scheduled_at' => $scheduledAt->toISOString(),
                        'reason' => $reason,
                        'recurring' => $recurring,
                        'interval_days' => $intervalDays,
                        'scheduled_by' => auth()->id(),
                        'created_at' => now()->toISOString(),
                    ]
                ])
            ]);

            return response()->json([
                'message' => 'Key rotation scheduled successfully',
                'scheduled_at' => $scheduledAt->toISOString(),
                'recurring' => $recurring,
                'interval_days' => $intervalDays,
                'reason' => $reason,
                'job_scheduled' => true,
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to schedule key rotation', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
                'validated' => $validated,
            ]);

            return response()->json([
                'error' => 'Failed to schedule key rotation: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function verifyMessage(Request $request)
    {
        $validated = $request->validate([
            'content' => 'required|string',
            'hash' => 'required|string',
        ]);

        $isValid = $this->encryptionService->verifyMessageHash(
            $validated['content'],
            $validated['hash']
        );

        return response()->json(['valid' => $isValid]);
    }

    public function registerKey(Request $request)
    {
        $validated = $request->validate([
            'public_key' => 'required|string',
        ]);

        try {
            // Store or update the user's public key for future use
            // This could be stored in a user_encryption_keys table or user profile
            auth()->user()->update([
                'public_key' => $validated['public_key'],
            ]);

            return response()->json([
                'message' => 'Public key registered successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to register public key'], 500);
        }
    }

    public function testEncryption(Request $request)
    {
        $validated = $request->validate([
            'message' => 'required|string|max:1000',
        ]);

        try {
            $keyPair = $this->encryptionService->generateKeyPair();
            $symmetricKey = $this->encryptionService->generateSymmetricKey();

            $encryptedSymKey = $this->encryptionService->encryptSymmetricKey(
                $symmetricKey,
                $keyPair['public']
            );

            $encrypted = $this->encryptionService->encryptMessage(
                $validated['message'],
                $symmetricKey
            );

            $decryptedSymKey = $this->encryptionService->decryptSymmetricKey(
                $encryptedSymKey,
                $keyPair['private']
            );

            $decrypted = $this->encryptionService->decryptMessage(
                $encrypted['data'],
                $encrypted['iv'],
                $decryptedSymKey
            );

            $hashVerified = $this->encryptionService->verifyMessageHash(
                $decrypted,
                $encrypted['hash']
            );

            return response()->json([
                'original' => $validated['message'],
                'encrypted' => $encrypted,
                'decrypted' => $decrypted,
                'hash_verified' => $hashVerified,
                'success' => $decrypted === $validated['message'] && $hashVerified,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Encryption test failed: '.$e->getMessage(),
            ], 500);
        }
    }

    private function getUserKeyPair(string $userId): array
    {
        return $this->encryptionService->generateKeyPair();
    }

    public function createBackup(Request $request)
    {
        $validated = $request->validate([
            'password' => 'required|string|min:8',
        ]);

        try {
            // Get user's conversation keys (private key is not stored on server for security)
            $user = auth()->user();
            $keyData = [
                'user_id' => $user->id,
                'public_key' => $user->public_key,
                'conversations' => $user->encryptionKeys()->with('conversation')->get()->map(function ($key) {
                    return [
                        'conversation_id' => $key->conversation_id,
                        'conversation_name' => $key->conversation->name ?? 'Direct Chat',
                        'encrypted_key' => $key->encrypted_key,
                        'created_at' => $key->created_at,
                    ];
                })->toArray(),
            ];

            $backup = $this->encryptionService->createBackupEncryptionKey(
                $validated['password'],
                $keyData
            );

            return response()->json([
                'backup_data' => $backup,
                'created_at' => now()->toISOString(),
                'conversations_count' => count($keyData['conversations']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create backup',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function restoreBackup(Request $request)
    {
        $validated = $request->validate([
            'backup_data' => 'required|string',
            'password' => 'required|string',
        ]);

        try {
            $keyData = $this->encryptionService->restoreFromBackup(
                $validated['backup_data'],
                $validated['password']
            );

            $user = auth()->user();
            $restoredConversations = 0;
            $errors = [];

            // Validate that this backup belongs to the current user
            if (isset($keyData['user_id']) && $keyData['user_id'] !== $user->id) {
                return response()->json([
                    'error' => 'Backup does not belong to current user',
                ], 403);
            }

            // Private keys are not stored on server for security reasons
            // They should be restored client-side only
            $privateKeyRestored = false;

            // Restore conversation keys
            if (isset($keyData['conversations']) && is_array($keyData['conversations'])) {
                foreach ($keyData['conversations'] as $conversationData) {
                    try {
                        // Verify conversation exists and user has access
                        $conversation = \App\Models\Chat\Conversation::find($conversationData['conversation_id']);

                        if (! $conversation) {
                            $errors[$conversationData['conversation_id']] = 'Conversation not found';

                            continue;
                        }

                        // Check if user is still a participant
                        $isParticipant = $conversation->participants()
                            ->where('user_id', $user->id)
                            ->whereNull('left_at')
                            ->exists();

                        if (! $isParticipant) {
                            $errors[$conversationData['conversation_id']] = 'User no longer participant in conversation';

                            continue;
                        }

                        // Restore or update encryption key
                        $existingKey = EncryptionKey::where('conversation_id', $conversation->id)
                            ->where('user_id', $user->id)
                            ->first();

                        if ($existingKey) {
                            // Update existing key
                            $existingKey->update([
                                'encrypted_key' => $conversationData['encrypted_key'],
                                'public_key' => $user->public_key ?? $keyData['public_key'] ?? null,
                                'is_active' => true,
                            ]);
                        } else {
                            // Get or create a device for this user (for backward compatibility)
                            $device = \App\Models\UserDevice::where('user_id', $user->id)
                                ->where('is_trusted', true)
                                ->first();

                            if (!$device) {
                                $device = \App\Models\UserDevice::create([
                                    'user_id' => $user->id,
                                    'device_name' => 'Backup Restore Device',
                                    'device_type' => 'web',
                                    'device_fingerprint' => 'restore-' . $user->id . '-' . uniqid(),
                                    'platform_info' => json_encode(['os' => 'web', 'browser' => 'restore']),
                                    'public_key' => $user->public_key ?? $keyData['public_key'] ?? null,
                                    'is_trusted' => true,
                                    'device_capabilities' => json_encode(['messaging', 'encryption']),
                                    'security_level' => 'medium',
                                ]);
                            }

                            // Create new encryption key record
                            EncryptionKey::create([
                                'conversation_id' => $conversation->id,
                                'user_id' => $user->id,
                                'device_id' => $device->id,
                                'device_fingerprint' => $device->device_fingerprint,
                                'encrypted_key' => $conversationData['encrypted_key'],
                                'public_key' => $user->public_key ?? $keyData['public_key'] ?? null,
                                'is_active' => true,
                                'key_version' => 1,
                                'algorithm' => 'RSA-OAEP',
                                'key_strength' => 4096,
                            ]);
                        }

                        $restoredConversations++;

                    } catch (\Exception $e) {
                        $errors[$conversationData['conversation_id'] ?? 'unknown'] = $e->getMessage();
                    }
                }
            }

            // Log the restoration
            \Log::info('User restored encryption backup', [
                'user_id' => $user->id,
                'conversations_restored' => $restoredConversations,
                'total_conversations_in_backup' => count($keyData['conversations'] ?? []),
                'errors_count' => count($errors),
                'restored_at' => now()->toISOString(),
            ]);

            $response = [
                'message' => 'Backup restored successfully',
                'conversations_restored' => $restoredConversations,
                'total_in_backup' => count($keyData['conversations'] ?? []),
                'private_key_restored' => $privateKeyRestored,
            ];

            if (! empty($errors)) {
                $response['warnings'] = $errors;
            }

            return response()->json($response);

        } catch (\Exception $e) {
            \Log::error('Backup restoration failed', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Failed to restore backup',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function getEncryptionHealth()
    {
        try {
            $health = $this->encryptionService->validateEncryptionHealth();

            return response()->json($health);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'unhealthy',
                'error' => 'Health check failed: '.$e->getMessage(),
            ], 500);
        }
    }

    public function bulkDecryptMessages(Request $request)
    {
        $validated = $request->validate([
            'conversation_id' => 'required|string',
            'message_ids' => 'required|array',
            'message_ids.*' => 'string',
        ]);

        try {
            $conversation = Conversation::findOrFail($validated['conversation_id']);
            $this->authorize('view', $conversation);

            $encryptionKey = auth()->user()
                ->getActiveEncryptionKeyForConversation($conversation->id);

            if (! $encryptionKey) {
                return response()->json(['error' => 'No encryption key found'], 404);
            }

            // Get messages and their encrypted content
            $messages = $conversation->messages()
                ->whereIn('id', $validated['message_ids'])
                ->get(['id', 'encrypted_content', 'content_hash', 'content_hmac']);

            $encryptedMessages = [];
            foreach ($messages as $message) {
                if ($message->encrypted_content) {
                    $encryptedMessages[$message->id] = [
                        'data' => $message->encrypted_content,
                        'hmac' => $message->content_hmac,
                        'hash' => $message->content_hash,
                    ];
                }
            }

            // Get private key from cache or user storage
            $cacheKey = 'user_private_key_'.auth()->id();
            $encryptedPrivateKey = Cache::get($cacheKey);

            if (! $encryptedPrivateKey) {
                return response()->json(['error' => 'Private key not available'], 404);
            }

            $privateKey = $this->encryptionService->decryptFromStorage($encryptedPrivateKey);
            $symmetricKey = $encryptionKey->decryptSymmetricKey($privateKey);

            $result = $this->encryptionService->bulkDecryptMessages(
                $encryptedMessages,
                $symmetricKey
            );

            return response()->json([
                'decrypted_messages' => $result['decrypted'],
                'errors' => $result['errors'],
                'success_count' => $result['success_count'],
                'total_count' => $result['total_count'],
            ]);

        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            throw $e; // Let authorization exceptions bubble up to return proper 403
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Bulk decryption failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function setupConversationEncryption(Request $request, Conversation $conversation)
    {
        $this->authorize('update', $conversation);

        $validated = $request->validate([
            'encrypted_keys' => 'required|array',
            'encrypted_keys.*.publicKey' => 'required|string',
            'encrypted_keys.*.encryptedKey' => 'required|string',
            'encrypted_keys.*.userId' => 'nullable|string',
        ]);

        try {
            $participants = $conversation->activeParticipants()->with('user')->get();
            $successCount = 0;
            $errors = [];

            // Match encrypted keys with participants by public key
            foreach ($validated['encrypted_keys'] as $keyData) {
                $participant = $participants->first(function ($p) use ($keyData) {
                    // Normalize whitespace for comparison
                    $userKey = trim(preg_replace('/\s+/', ' ', $p->user->public_key ?? ''));
                    $providedKey = trim(preg_replace('/\s+/', ' ', $keyData['publicKey']));
                    return $userKey === $providedKey;
                });

                if (! $participant) {
                    $errors[] = [
                        'public_key' => substr($keyData['publicKey'], 0, 50).'...',
                        'error' => 'No matching participant found for public key',
                    ];

                    continue;
                }

                try {
                    // Get or create a device for this user (for backward compatibility)
                    $device = \App\Models\UserDevice::where('user_id', $participant->user_id)
                        ->where('is_trusted', true)
                        ->first();

                    if (!$device) {
                        $device = \App\Models\UserDevice::create([
                            'user_id' => $participant->user_id,
                            'device_name' => 'Legacy Device',
                            'device_type' => 'web',
                            'device_fingerprint' => 'legacy-' . $participant->user_id . '-' . uniqid(),
                            'platform_info' => json_encode(['os' => 'web', 'browser' => 'legacy']),
                            'public_key' => $keyData['publicKey'],
                            'is_trusted' => true,
                            'device_capabilities' => json_encode(['messaging', 'encryption']),
                            'security_level' => 'medium',
                        ]);
                    }

                    // Create encryption key for this participant's device
                    EncryptionKey::create([
                        'conversation_id' => $conversation->id,
                        'user_id' => $participant->user_id,
                        'device_id' => $device->id,
                        'device_fingerprint' => $device->device_fingerprint,
                        'encrypted_key' => $keyData['encryptedKey'],
                        'public_key' => $keyData['publicKey'],
                        'is_active' => true,
                        'key_version' => 1,
                        'algorithm' => 'RSA-OAEP',
                        'key_strength' => 4096,
                    ]);

                    $successCount++;

                } catch (\Exception $e) {
                    $errors[] = [
                        'user_id' => $participant->user_id,
                        'error' => $e->getMessage(),
                    ];
                }
            }

            // Log the setup
            \Log::info('Conversation encryption setup completed', [
                'conversation_id' => $conversation->id,
                'initiated_by' => auth()->id(),
                'participants_count' => $participants->count(),
                'keys_created' => $successCount,
                'errors_count' => count($errors),
            ]);

            $response = [
                'message' => 'Conversation encryption setup completed',
                'keys_created' => $successCount,
                'total_participants' => $participants->count(),
                'setup_id' => \Str::uuid(),
                'timestamp' => now()->toISOString(),
            ];

            if (! empty($errors)) {
                $response['warnings'] = $errors;
            }

            return response()->json($response);

        } catch (\Exception $e) {
            \Log::error('Failed to setup conversation encryption', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Failed to setup conversation encryption',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    public function setupConversationEncryptionMultiDevice(Request $request, Conversation $conversation)
    {
        $this->authorize('update', $conversation);

        $validated = $request->validate([
            'device_keys' => 'required|array',
            'device_keys.*.device_id' => 'required|string|exists:user_devices,id',
            'initiating_device_id' => 'required|string|exists:user_devices,id',
        ]);

        try {
            $initiatingDevice = UserDevice::findOrFail($validated['initiating_device_id']);

            if ($initiatingDevice->user_id !== auth()->id()) {
                return response()->json(['error' => 'Invalid initiating device'], 403);
            }

            $results = $this->multiDeviceService->setupConversationEncryptionForDevices(
                $conversation,
                $validated['device_keys'],
                $initiatingDevice
            );

            return response()->json([
                'message' => 'Multi-device conversation encryption setup completed',
                'key_version' => $results['key_version'],
                'created_keys' => $results['created_keys'],
                'failed_keys' => $results['failed_keys'],
                'setup_id' => \Str::uuid(),
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to setup multi-device conversation encryption',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    public function rotateConversationKeyMultiDevice(Request $request, Conversation $conversation)
    {
        $this->authorize('update', $conversation);

        $validated = $request->validate([
            'initiating_device_id' => 'required|string|exists:user_devices,id',
            'reason' => 'nullable|string|max:255',
        ]);

        try {
            $initiatingDevice = UserDevice::findOrFail($validated['initiating_device_id']);

            if ($initiatingDevice->user_id !== auth()->id()) {
                return response()->json(['error' => 'Invalid initiating device'], 403);
            }

            $results = $this->multiDeviceService->rotateConversationKeys($conversation, $initiatingDevice);

            return response()->json([
                'message' => 'Multi-device conversation key rotation completed',
                'key_version' => $results['key_version'],
                'rotated_devices' => $results['rotated_devices'],
                'failed_devices' => $results['failed_devices'],
                'rotation_id' => \Str::uuid(),
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to rotate multi-device conversation key',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    public function acceptKeyShare(Request $request, DeviceKeyShare $keyShare)
    {
        if ($keyShare->to_device_id !== $request->input('device_id')) {
            return response()->json(['error' => 'Key share does not belong to specified device'], 403);
        }

        if ($keyShare->user_id !== auth()->id()) {
            return response()->json(['error' => 'Key share does not belong to current user'], 403);
        }

        $validated = $request->validate([
            'device_id' => 'required|string|exists:user_devices,id',
            'decrypted_symmetric_key' => 'required|string', // Client decrypted the key
        ]);

        try {
            $device = UserDevice::findOrFail($validated['device_id']);

            $encryptionKey = $this->multiDeviceService->acceptKeyShare(
                $device,
                $keyShare,
                $validated['decrypted_symmetric_key']
            );

            return response()->json([
                'message' => 'Key share accepted successfully',
                'encryption_key_id' => $encryptionKey->id,
                'conversation_id' => $keyShare->conversation_id,
                'key_version' => $encryptionKey->key_version,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to accept key share',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    public function getDeviceConversationKey(Request $request, Conversation $conversation)
    {
        $this->authorize('view', $conversation);

        $validated = $request->validate([
            'device_id' => 'required|string|exists:user_devices,id',
        ]);

        try {
            $device = UserDevice::findOrFail($validated['device_id']);

            if ($device->user_id !== auth()->id()) {
                return response()->json(['error' => 'Device does not belong to current user'], 403);
            }

            $encryptionKey = auth()->user()
                ->getActiveEncryptionKeyForConversationAndDevice($conversation->id, $device->id);

            if (! $encryptionKey) {
                return response()->json(['error' => 'No encryption key found for this device and conversation'], 404);
            }

            return response()->json([
                'key_id' => $encryptionKey->id,
                'encrypted_key' => $encryptionKey->encrypted_key,
                'public_key' => $encryptionKey->public_key,
                'key_version' => $encryptionKey->key_version,
                'device_fingerprint' => $encryptionKey->device_fingerprint,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to get device conversation key',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    public function revokeDeviceAccess(Request $request, UserDevice $device)
    {
        if ($device->user_id !== auth()->id()) {
            return response()->json(['error' => 'Device does not belong to current user'], 403);
        }

        $validated = $request->validate([
            'conversation_id' => 'nullable|string|exists:chat_conversations,id',
            'reason' => 'nullable|string|max:255',
        ]);

        try {
            $results = $this->multiDeviceService->revokeDeviceAccess(
                $device,
                $validated['conversation_id'] ?? null
            );

            return response()->json([
                'message' => 'Device access revoked successfully',
                'revoked_keys' => $results['revoked_keys'],
                'cancelled_shares' => $results['cancelled_shares'],
                'device_id' => $device->id,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to revoke device access',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    public function getMultiDeviceHealth(Request $request)
    {
        $validated = $request->validate([
            'device_id' => 'nullable|string|exists:user_devices,id',
        ]);

        try {
            $health = $this->encryptionService->validateEncryptionHealth();

            if (isset($validated['device_id'])) {
                $device = UserDevice::findOrFail($validated['device_id']);
                if ($device->user_id === auth()->id()) {
                    $deviceSummary = $this->multiDeviceService->getDeviceEncryptionSummary($device);
                    $health['device_status'] = $deviceSummary;
                }
            }

            // Add multi-device specific checks
            $userDevices = auth()->user()->activeDevices()->count();
            $trustedDevices = auth()->user()->trustedActiveDevices()->count();

            $health['multi_device'] = [
                'total_devices' => $userDevices,
                'trusted_devices' => $trustedDevices,
                'untrusted_devices' => $userDevices - $trustedDevices,
            ];

            return response()->json($health);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'unhealthy',
                'error' => 'Multi-device health check failed: '.$e->getMessage(),
            ], 500);
        }
    }
}
