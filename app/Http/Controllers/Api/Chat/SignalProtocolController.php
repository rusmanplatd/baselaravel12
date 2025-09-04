<?php

namespace App\Http\Controllers\Api\Chat;

use App\Http\Controllers\Controller;
use App\Models\Chat\Conversation;
use App\Models\Chat\SignalIdentityKey;
use App\Models\Chat\SignalMessage;
use App\Models\Chat\SignalOnetimePrekey;
use App\Models\Chat\SignalPreKeyRequest;
use App\Models\Chat\SignalSession;
use App\Models\Chat\SignalSignedPrekey;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SignalProtocolController extends Controller
{
    /**
     * Upload prekey bundle for current user.
     */
    public function uploadPreKeyBundle(Request $request): JsonResponse
    {
        $request->validate([
            'registration_id' => 'required|integer|min:1|max:16384',
            'identity_key' => 'required|string',
            'signed_pre_key' => 'required|array',
            'signed_pre_key.key_id' => 'required|integer',
            'signed_pre_key.public_key' => 'required|string',
            'signed_pre_key.signature' => 'required|string',
            'one_time_pre_keys' => 'required|array|min:1|max:100',
            'one_time_pre_keys.*.key_id' => 'required|integer',
            'one_time_pre_keys.*.public_key' => 'required|string',
        ]);

        $user = Auth::user();

        try {
            DB::beginTransaction();

            // Create or update identity key
            $identityKey = SignalIdentityKey::updateOrCreate(
                ['user_id' => $user->id, 'registration_id' => $request->registration_id],
                [
                    'public_key' => $request->identity_key,
                    'key_fingerprint' => SignalIdentityKey::calculateFingerprint($request->identity_key),
                    'is_active' => true,
                ]
            );

            // Deactivate old identity keys
            SignalIdentityKey::where('user_id', $user->id)
                ->where('id', '!=', $identityKey->id)
                ->update(['is_active' => false]);

            // Create signed prekey
            SignalSignedPrekey::create([
                'user_id' => $user->id,
                'key_id' => $request->signed_pre_key['key_id'],
                'public_key' => $request->signed_pre_key['public_key'],
                'signature' => $request->signed_pre_key['signature'],
                'generated_at' => now(),
                'is_active' => true,
            ]);

            // Deactivate old signed prekeys (keep latest 3)
            SignalSignedPrekey::cleanupOldKeys($user->id, 3);

            // Create one-time prekeys
            $onetimePrekeys = collect($request->one_time_pre_keys)->map(function ($prekey) use ($user) {
                return [
                    'user_id' => $user->id,
                    'key_id' => $prekey['key_id'],
                    'public_key' => $prekey['public_key'],
                    'is_used' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            });

            SignalOnetimePrekey::insert($onetimePrekeys->toArray());

            // Update user stats
            $this->updateUserStats($user->id);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Prekey bundle uploaded successfully',
                'stats' => [
                    'identity_key_id' => $identityKey->id,
                    'signed_prekey_count' => SignalSignedPrekey::where('user_id', $user->id)->where('is_active', true)->count(),
                    'onetime_prekey_count' => SignalOnetimePrekey::where('user_id', $user->id)->where('is_used', false)->count(),
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload prekey bundle',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get prekey bundle for a specific user.
     */
    public function getPreKeyBundle(Request $request, int $userId): JsonResponse
    {
        $targetUser = User::findOrFail($userId);
        $requestingUser = Auth::user();

        // Get identity key
        $identityKey = SignalIdentityKey::getCurrentForUser($userId);
        if (! $identityKey) {
            return response()->json([
                'success' => false,
                'message' => 'User has no identity key registered',
            ], 404);
        }

        // Get current signed prekey
        $signedPrekey = SignalSignedPrekey::getCurrentForUser($userId);
        if (! $signedPrekey) {
            return response()->json([
                'success' => false,
                'message' => 'User has no signed prekey available',
            ], 404);
        }

        // Get an unused one-time prekey (if available)
        $onetimePrekey = SignalOnetimePrekey::getUnusedForUser($userId);

        // Mark one-time prekey as used if we found one
        if ($onetimePrekey) {
            $onetimePrekey->markAsUsed($requestingUser->id);
        }

        // Create prekey request log
        $requestId = Str::uuid();
        SignalPreKeyRequest::create([
            'requester_user_id' => $requestingUser->id,
            'target_user_id' => $userId,
            'identity_key_id' => $identityKey->id,
            'signed_prekey_id' => $signedPrekey->id,
            'onetime_prekey_id' => $onetimePrekey?->id,
            'request_id' => $requestId,
            'bundle_data' => [
                'registration_id' => $identityKey->registration_id,
                'identity_key' => $identityKey->public_key,
                'signed_pre_key' => [
                    'key_id' => $signedPrekey->key_id,
                    'public_key' => $signedPrekey->public_key,
                    'signature' => $signedPrekey->signature,
                ],
                'one_time_pre_key' => $onetimePrekey ? [
                    'key_id' => $onetimePrekey->key_id,
                    'public_key' => $onetimePrekey->public_key,
                ] : null,
            ],
            'is_consumed' => false,
        ]);

        $response = [
            'success' => true,
            'request_id' => $requestId,
            'registration_id' => $identityKey->registration_id,
            'identity_key' => $identityKey->public_key,
            'signed_pre_key' => [
                'key_id' => $signedPrekey->key_id,
                'public_key' => $signedPrekey->public_key,
                'signature' => $signedPrekey->signature,
            ],
        ];

        if ($onetimePrekey) {
            $response['one_time_pre_key'] = [
                'key_id' => $onetimePrekey->key_id,
                'public_key' => $onetimePrekey->public_key,
            ];
        }

        return response()->json($response);
    }

    /**
     * Send a Signal Protocol message.
     */
    public function sendSignalMessage(Request $request): JsonResponse
    {
        $request->validate([
            'conversation_id' => 'required|integer|exists:conversations,id',
            'recipient_user_id' => 'required|integer|exists:users,id',
            'signal_message' => 'required|array',
            'signal_message.type' => 'required|in:prekey,normal',
            'signal_message.version' => 'required|integer',
            'signal_message.message' => 'required|array',
            'delivery_options' => 'sometimes|array',
        ]);

        $sender = Auth::user();
        $conversationId = $request->conversation_id;
        $recipientUserId = $request->recipient_user_id;
        $signalMessage = $request->signal_message;
        $deliveryOptions = $request->delivery_options ?? [];

        try {
            DB::beginTransaction();

            // Verify conversation access
            $conversation = Conversation::findOrFail($conversationId);
            if (! $conversation->participants()->where('user_id', $sender->id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not a participant in this conversation',
                ], 403);
            }

            // Get or create session
            $session = SignalSession::getActiveSession($conversationId, $sender->id, $recipientUserId);

            if (! $session && $signalMessage['type'] === 'normal') {
                return response()->json([
                    'success' => false,
                    'message' => 'No active session found. Please send a prekey message first.',
                ], 400);
            }

            // Create session for prekey messages
            if (! $session && $signalMessage['type'] === 'prekey') {
                $session = SignalSession::create([
                    'session_id' => Str::uuid(),
                    'conversation_id' => $conversationId,
                    'local_user_id' => $sender->id,
                    'remote_user_id' => $recipientUserId,
                    'local_registration_id' => $signalMessage['registration_id'] ?? 0,
                    'remote_registration_id' => 0, // Will be updated when we get response
                    'remote_identity_key' => '', // Will be updated
                    'session_state_encrypted' => '', // Will be managed by client
                    'current_sending_chain' => 0,
                    'current_receiving_chain' => 0,
                    'is_active' => true,
                    'verification_status' => 'unverified',
                    'protocol_version' => (string) $signalMessage['version'],
                    'last_activity_at' => now(),
                ]);
            }

            // Create message record
            $messageId = Str::uuid();
            $message = SignalMessage::create([
                'message_id' => $messageId,
                'conversation_id' => $conversationId,
                'session_id' => $session->id,
                'sender_user_id' => $sender->id,
                'recipient_user_id' => $recipientUserId,
                'message_type' => $signalMessage['type'],
                'protocol_version' => $signalMessage['version'],
                'registration_id' => $signalMessage['registration_id'] ?? null,
                'prekey_id' => $signalMessage['prekey_id'] ?? null,
                'signed_prekey_id' => $signalMessage['signed_prekey_id'] ?? null,
                'base_key' => $signalMessage['base_key'] ?? null,
                'identity_key' => $signalMessage['identity_key'] ?? null,
                'ratchet_message' => $signalMessage['message'],
                'delivery_options' => $deliveryOptions,
                'delivery_status' => 'sent',
                'sent_at' => now(),
            ]);

            // Update session stats
            $session->incrementMessagesSent();

            // Update user stats
            $this->updateUserStats($sender->id);

            DB::commit();

            // In a real implementation, you would push this message to the recipient
            // via WebSocket, push notification, etc.

            return response()->json([
                'success' => true,
                'message_id' => $messageId,
                'session_id' => $session->session_id,
                'delivery_status' => 'sent',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to send Signal message',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get Signal Protocol session information.
     */
    public function getSessionInfo(Request $request): JsonResponse
    {
        $request->validate([
            'conversation_id' => 'required|integer|exists:conversations,id',
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $user = Auth::user();
        $conversationId = $request->conversation_id;
        $targetUserId = $request->user_id;

        $session = SignalSession::getActiveSession($conversationId, $user->id, $targetUserId);

        if (! $session) {
            return response()->json([
                'success' => false,
                'message' => 'No active session found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'session' => [
                'session_id' => $session->session_id,
                'remote_user_id' => $session->remote_user_id,
                'is_active' => $session->is_active,
                'verification_status' => $session->verification_status,
                'protocol_version' => $session->protocol_version,
                'messages_sent' => $session->messages_sent,
                'messages_received' => $session->messages_received,
                'key_rotations' => $session->key_rotations,
                'last_activity_at' => $session->last_activity_at,
                'created_at' => $session->created_at,
                'remote_identity_fingerprint' => $session->getRemoteIdentityFingerprint(),
                'is_verified' => $session->isVerified(),
                'age_in_days' => $session->getAgeInDays(),
            ],
        ]);
    }

    /**
     * Verify user identity.
     */
    public function verifyUserIdentity(Request $request): JsonResponse
    {
        $request->validate([
            'conversation_id' => 'required|integer|exists:conversations,id',
            'user_id' => 'required|integer|exists:users,id',
            'fingerprint' => 'required|string|size:64', // SHA-256 hex
            'verification_method' => 'required|in:fingerprint,qr_code,safety_numbers',
        ]);

        $verifier = Auth::user();
        $conversationId = $request->conversation_id;
        $targetUserId = $request->user_id;
        $providedFingerprint = $request->fingerprint;
        $verificationMethod = $request->verification_method;

        try {
            DB::beginTransaction();

            // Get active session
            $session = SignalSession::getActiveSession($conversationId, $verifier->id, $targetUserId);
            if (! $session) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active session found',
                ], 404);
            }

            // Calculate actual fingerprint
            $actualFingerprint = $session->getRemoteIdentityFingerprint();
            $verificationSuccessful = hash_equals($actualFingerprint, $providedFingerprint);

            // Log verification attempt
            \App\Models\Chat\SignalIdentityVerification::create([
                'verifier_user_id' => $verifier->id,
                'target_user_id' => $targetUserId,
                'session_id' => $session->id,
                'verification_method' => $verificationMethod,
                'provided_fingerprint' => $providedFingerprint,
                'actual_fingerprint' => $actualFingerprint,
                'verification_successful' => $verificationSuccessful,
                'verification_token' => $verificationSuccessful ? Str::random(32) : null,
            ]);

            // Update session verification status
            if ($verificationSuccessful) {
                $session->updateVerificationStatus('verified');
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'verification_successful' => $verificationSuccessful,
                'session_verified' => $verificationSuccessful,
                'message' => $verificationSuccessful
                    ? 'User identity verified successfully'
                    : 'User identity verification failed',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Verification process failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Rotate session keys.
     */
    public function rotateSessionKeys(Request $request): JsonResponse
    {
        $request->validate([
            'session_id' => 'required|string',
            'reason' => 'sometimes|string|max:255',
        ]);

        $user = Auth::user();
        $sessionId = $request->session_id;
        $reason = $request->reason ?? 'Manual rotation';

        try {
            DB::beginTransaction();

            // Find session
            $session = SignalSession::where('session_id', $sessionId)
                ->where(function ($query) use ($user) {
                    $query->where('local_user_id', $user->id)
                        ->orWhere('remote_user_id', $user->id);
                })
                ->where('is_active', true)
                ->firstOrFail();

            // Log key rotation
            \App\Models\Chat\SignalKeyRotation::create([
                'user_id' => $user->id,
                'session_id' => $session->id,
                'rotation_type' => 'session_keys',
                'reason' => $reason,
                'rotation_metadata' => [
                    'old_sending_chain' => $session->current_sending_chain,
                    'old_receiving_chain' => $session->current_receiving_chain,
                ],
            ]);

            // Reset chain counters (actual key rotation happens on client side)
            $session->update([
                'current_sending_chain' => 0,
                'current_receiving_chain' => 0,
            ]);

            // Increment rotation counter
            $session->incrementKeyRotations();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Session keys rotated successfully',
                'session_id' => $session->session_id,
                'key_rotations' => $session->key_rotations,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to rotate session keys',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get Signal Protocol statistics.
     */
    public function getStatistics(Request $request): JsonResponse
    {
        $user = Auth::user();

        $stats = [
            'user_id' => $user->id,
            'identity_keys' => SignalIdentityKey::where('user_id', $user->id)->where('is_active', true)->count(),
            'signed_prekeys' => SignalSignedPrekey::where('user_id', $user->id)->where('is_active', true)->count(),
            'onetime_prekeys_available' => SignalOnetimePrekey::getUnusedCountForUser($user->id),
            'onetime_prekeys_used' => SignalOnetimePrekey::where('user_id', $user->id)->where('is_used', true)->count(),
            'active_sessions' => SignalSession::where('local_user_id', $user->id)->where('is_active', true)->count(),
            'total_sessions' => SignalSession::where('local_user_id', $user->id)->count(),
            'messages_sent' => SignalMessage::where('sender_user_id', $user->id)->count(),
            'messages_received' => SignalMessage::where('recipient_user_id', $user->id)->count(),
            'verified_sessions' => SignalSession::where('local_user_id', $user->id)
                ->whereIn('verification_status', ['verified', 'trusted'])
                ->count(),
            'key_rotations_performed' => \App\Models\Chat\SignalKeyRotation::where('user_id', $user->id)->count(),
        ];

        // Get recent activity
        $recentSessions = SignalSession::where('local_user_id', $user->id)
            ->where('is_active', true)
            ->orderBy('last_activity_at', 'desc')
            ->limit(5)
            ->with(['remoteUser:id,name', 'conversation:id,name'])
            ->get()
            ->map(function ($session) {
                return [
                    'session_id' => $session->session_id,
                    'remote_user' => $session->remoteUser->name,
                    'conversation' => $session->conversation->name ?? 'Direct Message',
                    'last_activity' => $session->last_activity_at,
                    'messages_exchanged' => $session->messages_sent + $session->messages_received,
                    'verification_status' => $session->verification_status,
                ];
            });

        return response()->json([
            'success' => true,
            'statistics' => $stats,
            'recent_sessions' => $recentSessions,
            'health_score' => $this->calculateHealthScore($user->id),
        ]);
    }

    /**
     * Update user statistics.
     */
    private function updateUserStats(string $userId): void
    {
        \App\Models\Chat\SignalProtocolStats::updateOrCreate(
            ['user_id' => $userId],
            [
                'active_sessions' => SignalSession::where('local_user_id', $userId)->where('is_active', true)->count(),
                'total_messages_sent' => SignalMessage::where('sender_user_id', $userId)->count(),
                'total_messages_received' => SignalMessage::where('recipient_user_id', $userId)->count(),
                'key_rotations_performed' => \App\Models\Chat\SignalKeyRotation::where('user_id', $userId)->count(),
                'identity_verifications' => \App\Models\Chat\SignalIdentityVerification::where('verifier_user_id', $userId)
                    ->where('verification_successful', true)->count(),
                'available_onetime_prekeys' => SignalOnetimePrekey::getUnusedCountForUser($userId),
                'last_session_activity' => SignalSession::where('local_user_id', $userId)
                    ->where('is_active', true)
                    ->max('last_activity_at'),
            ]
        );
    }

    /**
     * Calculate health score based on various metrics.
     */
    private function calculateHealthScore(string $userId): array
    {
        $score = 100;
        $issues = [];

        // Check if user has identity key
        $hasIdentityKey = SignalIdentityKey::where('user_id', $userId)->where('is_active', true)->exists();
        if (! $hasIdentityKey) {
            $score -= 30;
            $issues[] = 'No active identity key';
        }

        // Check signed prekey freshness (simplified check for now)
        $hasSignedPrekey = SignalSignedPrekey::where('user_id', $userId)->where('is_active', true)->exists();
        if (! $hasSignedPrekey) {
            $score -= 20;
            $issues[] = 'No signed prekey available';
        }

        // Check one-time prekey availability
        $onetimePrekeysCount = SignalOnetimePrekey::getUnusedCountForUser($userId);
        if ($onetimePrekeysCount < 10) {
            $score -= 15;
            $issues[] = 'Low one-time prekey count';
        }

        // Check for inactive sessions
        $inactiveSessions = SignalSession::where('local_user_id', $userId)
            ->where('is_active', true)
            ->where('last_activity_at', '<', now()->subDays(30))
            ->count();

        if ($inactiveSessions > 0) {
            $score -= ($inactiveSessions * 5);
            $issues[] = "Has {$inactiveSessions} inactive sessions";
        }

        return [
            'score' => max(0, $score),
            'status' => $score >= 80 ? 'healthy' : ($score >= 60 ? 'warning' : 'critical'),
            'issues' => $issues,
        ];
    }
}
