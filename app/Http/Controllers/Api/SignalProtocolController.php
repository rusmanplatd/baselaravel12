<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chat\SignalIdentityKey;
use App\Models\Chat\SignalSignedPrekey;
use App\Models\Chat\SignalOnetimePrekey;
use App\Models\Chat\SignalSession;
use App\Models\Chat\SignalMessage;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Signal Protocol API Controller
 * Handles prekey bundle management, session establishment, and message delivery
 */
class SignalProtocolController extends Controller
{
    /**
     * Upload a prekey bundle for the authenticated user
     */
    public function uploadBundle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'registration_id' => 'required|integer',
            'identity_key' => 'required|string',
            'signed_pre_key' => 'required|array',
            'signed_pre_key.key_id' => 'required|integer',
            'signed_pre_key.public_key' => 'required|string',
            'signed_pre_key.signature' => 'required|string',
            'signed_pre_key.quantum_public_key' => 'nullable|string',
            'signed_pre_key.quantum_algorithm' => 'nullable|string',
            'signed_pre_key.is_quantum_capable' => 'boolean',
            'one_time_pre_keys' => 'required|array|min:1|max:100',
            'one_time_pre_keys.*.key_id' => 'required|integer',
            'one_time_pre_keys.*.public_key' => 'required|string',
            'one_time_pre_keys.*.quantum_public_key' => 'nullable|string',
            'one_time_pre_keys.*.quantum_algorithm' => 'nullable|string',
            'one_time_pre_keys.*.is_quantum_capable' => 'boolean',
            'device_capabilities' => 'required|array',
            'device_capabilities.supported_algorithms' => 'required|array',
            'device_capabilities.quantum_capable' => 'boolean',
            'device_capabilities.fallback_algorithms' => 'required|array',
            'device_capabilities.protocol_version' => 'required|string',
            'device_capabilities.device_type' => 'required|string',
            'quantum_identity_key' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            $user = Auth::user();

            // Store or update identity key
            $identityKey = SignalIdentityKey::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'registration_id' => $validated['registration_id'],
                ],
                [
                    'public_key' => $validated['identity_key'],
                    'key_fingerprint' => hash('sha256', base64_decode($validated['identity_key'])),
                    'quantum_public_key' => $validated['quantum_identity_key'] ?? null,
                    'quantum_algorithm' => $validated['signed_pre_key']['quantum_algorithm'] ?? null,
                    'is_quantum_capable' => $validated['device_capabilities']['quantum_capable'] ?? false,
                    'quantum_version' => $this->determineQuantumVersion($validated),
                    'is_active' => true,
                ]
            );

            // Deactivate old signed prekeys and store new one
            SignalSignedPrekey::where('user_id', $user->id)->update(['is_active' => false]);
            
            $signedPreKey = SignalSignedPrekey::create([
                'user_id' => $user->id,
                'key_id' => $validated['signed_pre_key']['key_id'],
                'public_key' => $validated['signed_pre_key']['public_key'],
                'signature' => $validated['signed_pre_key']['signature'],
                'quantum_public_key' => $validated['signed_pre_key']['quantum_public_key'] ?? null,
                'quantum_algorithm' => $validated['signed_pre_key']['quantum_algorithm'] ?? null,
                'is_quantum_capable' => $validated['signed_pre_key']['is_quantum_capable'] ?? false,
                'generated_at' => now(),
                'is_active' => true,
            ]);

            // Remove old unused one-time prekeys and store new ones
            SignalOnetimePrekey::where('user_id', $user->id)
                ->where('is_used', false)
                ->delete();

            foreach ($validated['one_time_pre_keys'] as $prekeyData) {
                SignalOnetimePrekey::create([
                    'user_id' => $user->id,
                    'key_id' => $prekeyData['key_id'],
                    'public_key' => $prekeyData['public_key'],
                    'quantum_public_key' => $prekeyData['quantum_public_key'] ?? null,
                    'quantum_algorithm' => $prekeyData['quantum_algorithm'] ?? null,
                    'is_quantum_capable' => $prekeyData['is_quantum_capable'] ?? false,
                    'is_used' => false,
                ]);
            }

            DB::commit();

            Log::info('Signal Protocol prekey bundle uploaded', [
                'user_id' => $user->id,
                'registration_id' => $validated['registration_id'],
                'signed_prekey_id' => $validated['signed_pre_key']['key_id'],
                'onetime_prekeys_count' => count($validated['one_time_pre_keys']),
                'quantum_capable' => $validated['device_capabilities']['quantum_capable'],
                'supported_algorithms' => $validated['device_capabilities']['supported_algorithms'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Prekey bundle uploaded successfully',
                'bundle_info' => [
                    'identity_key_id' => $identityKey->id,
                    'signed_prekey_id' => $signedPreKey->id,
                    'onetime_prekeys_uploaded' => count($validated['one_time_pre_keys']),
                    'quantum_capable' => $validated['device_capabilities']['quantum_capable'],
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to upload Signal Protocol bundle', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload prekey bundle',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Fetch prekey bundle for a specific user
     */
    public function fetchPreKeyBundle(Request $request, string $userId): JsonResponse
    {
        try {
            $user = User::findOrFail($userId);

            // Get active identity key
            $identityKey = SignalIdentityKey::where('user_id', $userId)
                ->where('is_active', true)
                ->first();

            if (!$identityKey) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active identity key found for user',
                ], 404);
            }

            // Get active signed prekey
            $signedPreKey = SignalSignedPrekey::where('user_id', $userId)
                ->where('is_active', true)
                ->latest('created_at')
                ->first();

            if (!$signedPreKey) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active signed prekey found for user',
                ], 404);
            }

            // Get and mark one-time prekey as used
            $oneTimePreKey = SignalOnetimePrekey::where('user_id', $userId)
                ->where('is_used', false)
                ->first();

            if ($oneTimePreKey) {
                $oneTimePreKey->update([
                    'is_used' => true,
                    'used_at' => now(),
                    'used_by_user_id' => Auth::id(),
                ]);
            }

            // Determine device capabilities based on stored data
            $deviceCapabilities = $this->buildDeviceCapabilities($identityKey, $signedPreKey);

            $bundle = [
                'registration_id' => $identityKey->registration_id,
                'identity_key' => $identityKey->public_key,
                'signed_pre_key' => [
                    'key_id' => $signedPreKey->key_id,
                    'public_key' => $signedPreKey->public_key,
                    'signature' => $signedPreKey->signature,
                    'quantum_public_key' => $signedPreKey->quantum_public_key,
                    'quantum_algorithm' => $signedPreKey->quantum_algorithm,
                ],
                'device_capabilities' => $deviceCapabilities,
                'quantum_identity_key' => $identityKey->quantum_public_key,
            ];

            if ($oneTimePreKey) {
                $bundle['one_time_pre_key'] = [
                    'key_id' => $oneTimePreKey->key_id,
                    'public_key' => $oneTimePreKey->public_key,
                    'quantum_public_key' => $oneTimePreKey->quantum_public_key,
                    'quantum_algorithm' => $oneTimePreKey->quantum_algorithm,
                ];
            }

            Log::info('Signal Protocol prekey bundle fetched', [
                'target_user_id' => $userId,
                'requester_user_id' => Auth::id(),
                'quantum_capable' => $deviceCapabilities['quantum_capable'],
                'onetime_prekey_used' => $oneTimePreKey ? $oneTimePreKey->id : null,
            ]);

            return response()->json($bundle);

        } catch (\Exception $e) {
            Log::error('Failed to fetch Signal Protocol bundle', [
                'error' => $e->getMessage(),
                'target_user_id' => $userId,
                'requester_user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch prekey bundle',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Store a Signal Protocol message
     */
    public function storeMessage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'conversation_id' => 'required|string',
            'recipient_user_id' => 'required|string',
            'signal_message' => 'required|array',
            'signal_message.type' => 'required|in:prekey,normal',
            'signal_message.version' => 'required|integer',
            'signal_message.registration_id' => 'nullable|integer',
            'signal_message.prekey_id' => 'nullable|integer',
            'signal_message.signed_prekey_id' => 'nullable|integer',
            'signal_message.base_key' => 'nullable|string',
            'signal_message.identity_key' => 'nullable|string',
            'signal_message.message' => 'required|array',
            'signal_message.quantum_base_key' => 'nullable|string',
            'signal_message.quantum_identity_key' => 'nullable|string',
            'signal_message.quantum_algorithm' => 'nullable|string',
            'signal_message.is_quantum_resistant' => 'boolean',
            'signal_message.encryption_version' => 'required|integer',
            'delivery_options' => 'nullable|array',
        ]);

        try {
            DB::beginTransaction();

            $messageId = \Illuminate\Support\Str::ulid();

            // Find or create session
            $session = $this->findOrCreateSession(
                $validated['conversation_id'],
                Auth::id(),
                $validated['recipient_user_id'],
                $validated['signal_message']
            );

            // Store the message
            $message = SignalMessage::create([
                'message_id' => $messageId,
                'conversation_id' => $validated['conversation_id'],
                'session_id' => $session->id,
                'sender_user_id' => Auth::id(),
                'recipient_user_id' => $validated['recipient_user_id'],
                'message_type' => $validated['signal_message']['type'],
                'protocol_version' => $validated['signal_message']['version'],
                'registration_id' => $validated['signal_message']['registration_id'],
                'prekey_id' => $validated['signal_message']['prekey_id'],
                'signed_prekey_id' => $validated['signal_message']['signed_prekey_id'],
                'base_key' => $validated['signal_message']['base_key'],
                'identity_key' => $validated['signal_message']['identity_key'],
                'ratchet_message' => $validated['signal_message']['message'],
                'delivery_options' => $validated['delivery_options'] ?? [],
                'quantum_base_key' => $validated['signal_message']['quantum_base_key'],
                'quantum_identity_key' => $validated['signal_message']['quantum_identity_key'],
                'quantum_algorithm' => $validated['signal_message']['quantum_algorithm'],
                'is_quantum_resistant' => $validated['signal_message']['is_quantum_resistant'] ?? false,
                'encryption_version' => $validated['signal_message']['encryption_version'],
                'quantum_ratchet_data' => $this->extractQuantumRatchetData($validated['signal_message']),
                'delivery_status' => 'sent',
                'sent_at' => now(),
            ]);

            // Update session stats
            $session->increment('messages_sent');
            $session->touch('last_activity_at');

            DB::commit();

            Log::info('Signal Protocol message stored', [
                'message_id' => $messageId,
                'conversation_id' => $validated['conversation_id'],
                'sender_id' => Auth::id(),
                'recipient_id' => $validated['recipient_user_id'],
                'message_type' => $validated['signal_message']['type'],
                'quantum_resistant' => $validated['signal_message']['is_quantum_resistant'] ?? false,
                'algorithm' => $validated['signal_message']['quantum_algorithm'],
            ]);

            return response()->json([
                'success' => true,
                'message_id' => $messageId,
                'session_id' => $session->session_id,
                'delivery_status' => 'sent',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to store Signal Protocol message', [
                'error' => $e->getMessage(),
                'conversation_id' => $validated['conversation_id'] ?? null,
                'sender_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to store message',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get messages for a conversation
     */
    public function getMessages(Request $request, string $conversationId): JsonResponse
    {
        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:100',
            'offset' => 'nullable|integer|min:0',
            'include_quantum_data' => 'boolean',
        ]);

        try {
            $query = SignalMessage::where('conversation_id', $conversationId)
                ->where(function ($q) {
                    $q->where('sender_user_id', Auth::id())
                      ->orWhere('recipient_user_id', Auth::id());
                })
                ->with(['session', 'senderUser', 'recipientUser'])
                ->orderBy('sent_at', 'desc');

            if (isset($validated['limit'])) {
                $query->limit($validated['limit']);
            }

            if (isset($validated['offset'])) {
                $query->offset($validated['offset']);
            }

            $messages = $query->get();

            $messageData = $messages->map(function ($message) use ($validated) {
                $data = [
                    'message_id' => $message->message_id,
                    'sender_id' => $message->sender_user_id,
                    'recipient_id' => $message->recipient_user_id,
                    'message_type' => $message->message_type,
                    'protocol_version' => $message->protocol_version,
                    'ratchet_message' => $message->ratchet_message,
                    'delivery_status' => $message->delivery_status,
                    'sent_at' => $message->sent_at,
                    'is_quantum_resistant' => $message->is_quantum_resistant,
                    'encryption_version' => $message->encryption_version,
                ];

                if ($validated['include_quantum_data'] ?? false) {
                    $data['quantum_data'] = [
                        'algorithm' => $message->quantum_algorithm,
                        'quantum_ratchet_data' => $message->quantum_ratchet_data,
                    ];
                }

                return $data;
            });

            return response()->json([
                'success' => true,
                'messages' => $messageData,
                'total' => $messages->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get Signal Protocol messages', [
                'error' => $e->getMessage(),
                'conversation_id' => $conversationId,
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve messages',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Determine quantum version based on capabilities
     */
    private function determineQuantumVersion(array $data): int
    {
        if (!($data['device_capabilities']['quantum_capable'] ?? false)) {
            return 1; // Classical only
        }

        $supportedAlgorithms = $data['device_capabilities']['supported_algorithms'] ?? [];
        $hasQuantum = !empty(array_intersect($supportedAlgorithms, ['ML-KEM-1024', 'ML-KEM-768', 'ML-KEM-512']));
        $hasHybrid = in_array('HYBRID-RSA4096-MLKEM768', $supportedAlgorithms);

        if ($hasQuantum && !$hasHybrid) {
            return 3; // Full quantum
        } elseif ($hasHybrid || $hasQuantum) {
            return 2; // Hybrid
        }

        return 1; // Classical fallback
    }

    /**
     * Build device capabilities response
     */
    private function buildDeviceCapabilities(SignalIdentityKey $identityKey, SignalSignedPrekey $signedPreKey): array
    {
        $supportedAlgorithms = ['Curve25519', 'P-256', 'RSA-4096-OAEP', 'RSA-2048-OAEP'];
        
        if ($identityKey->is_quantum_capable) {
            $quantumAlgorithms = ['ML-KEM-1024', 'ML-KEM-768', 'ML-KEM-512', 'HYBRID-RSA4096-MLKEM768'];
            $supportedAlgorithms = array_merge($quantumAlgorithms, $supportedAlgorithms);
        }

        return [
            'supported_algorithms' => $supportedAlgorithms,
            'quantum_capable' => $identityKey->is_quantum_capable,
            'fallback_algorithms' => ['Curve25519', 'P-256', 'RSA-2048-OAEP'],
            'protocol_version' => '3.0',
            'device_type' => 'server', // This would be determined from user agent in real scenario
        ];
    }

    /**
     * Find or create a Signal session
     */
    private function findOrCreateSession(string $conversationId, string $localUserId, string $remoteUserId, array $messageData): SignalSession
    {
        $session = SignalSession::getActiveSession($conversationId, $localUserId, $remoteUserId);

        if (!$session) {
            $sessionId = "signal_{$conversationId}_{$localUserId}_{$remoteUserId}_" . time();
            
            $session = SignalSession::create([
                'session_id' => $sessionId,
                'conversation_id' => $conversationId,
                'local_user_id' => $localUserId,
                'remote_user_id' => $remoteUserId,
                'local_registration_id' => 0, // Would be set from user's identity
                'remote_registration_id' => $messageData['registration_id'] ?? 0,
                'remote_identity_key' => $messageData['identity_key'] ?? '',
                'session_state_encrypted' => json_encode([]), // Placeholder
                'verification_status' => 'unverified',
                'protocol_version' => '3.0',
                'quantum_algorithm' => $messageData['quantum_algorithm'] ?? null,
                'is_quantum_resistant' => $messageData['is_quantum_resistant'] ?? false,
                'quantum_version' => $messageData['encryption_version'] ?? 1,
                'last_activity_at' => now(),
            ]);
        }

        return $session;
    }

    /**
     * Extract quantum-specific ratchet data
     */
    private function extractQuantumRatchetData(array $messageData): ?array
    {
        if (!($messageData['is_quantum_resistant'] ?? false)) {
            return null;
        }

        return [
            'algorithm' => $messageData['quantum_algorithm'] ?? null,
            'version' => $messageData['encryption_version'] ?? 1,
            'quantum_header' => $messageData['message']['header']['quantumDH'] ?? null,
        ];
    }
}