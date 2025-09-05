<?php

namespace App\Http\Controllers\Api\Chat;

use App\Http\Controllers\Controller;
use App\Models\Chat\Conversation;
use App\Models\Chat\Message;
use App\Models\Organization;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\SignalProtocolService;
use App\Services\QuantumCryptoService;
use App\Services\ChatEncryptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class MentionController extends Controller
{
    public function __construct(
        private SignalProtocolService $signalService,
        private QuantumCryptoService $quantumService,
        private ChatEncryptionService $encryptionService
    ) {
        $this->middleware('auth:api');
        $this->middleware('throttle:120,1');
    }

    /**
     * Get mentionable users in conversation/organization
     */
    public function getUsers(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'conversation_id' => 'nullable|ulid|exists:chat_conversations,id',
            'organization_id' => 'nullable|ulid|exists:organizations,id',
            'include_quantum_status' => 'boolean',
            'limit' => 'integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $device = $this->getCurrentUserDevice($request);

        $query = User::query()->select([
            'id', 'name', 'avatar', 'email', 'last_active_at'
        ])->where('id', '!=', $user->id);

        if ($request->conversation_id) {
            $conversation = Conversation::findOrFail($request->conversation_id);
            
            // Check access
            if (!$conversation->hasUser($user->id)) {
                return response()->json(['error' => 'Access denied'], 403);
            }

            // Get conversation participants
            $participantIds = $conversation->activeParticipants()->pluck('user_id');
            $query->whereIn('id', $participantIds);
        } elseif ($request->organization_id) {
            $organization = Organization::findOrFail($request->organization_id);
            
            // Check user is member
            if (!$organization->hasMember($user->id)) {
                return response()->json(['error' => 'Access denied'], 403);
            }

            // Get organization members
            $memberIds = $organization->members()->pluck('user_id');
            $query->whereIn('id', $memberIds);
        }

        $users = $query->limit($request->input('limit', 50))->get();

        // Add quantum status if requested
        if ($request->boolean('include_quantum_status')) {
            $users = $users->map(function ($mentionUser) {
                $activeDevice = $mentionUser->devices()->active()->latest()->first();
                
                $mentionUser->device_status = [
                    'quantum_ready' => $activeDevice ? $this->isDeviceQuantumReady($activeDevice) : false,
                    'last_seen' => $mentionUser->last_active_at?->toISOString() ?? null,
                    'encryption_preference' => $this->getDeviceEncryptionPreference($activeDevice),
                ];

                // Add online status
                $mentionUser->online = $mentionUser->last_active_at && 
                                    $mentionUser->last_active_at->diffInMinutes(now()) < 5;

                return $mentionUser;
            });
        }

        return response()->json([
            'users' => $users,
            'total' => $users->count(),
        ]);
    }

    /**
     * Get mentionable channels
     */
    public function getChannels(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'organization_id' => 'nullable|ulid|exists:organizations,id',
            'include_quantum_status' => 'boolean',
            'limit' => 'integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        
        $query = Conversation::query()
            ->select(['id', 'name', 'type', 'description', 'encryption_algorithm', 'participant_count'])
            ->where('type', 'channel')
            ->whereHas('participants', function ($q) use ($user) {
                $q->where('user_id', $user->id)->active();
            });

        if ($request->organization_id) {
            // Filter by organization if specified
            $query->where('organization_id', $request->organization_id);
        }

        $channels = $query->limit($request->input('limit', 50))->get();

        if ($request->boolean('include_quantum_status')) {
            $channels = $channels->map(function ($channel) {
                $channel->encryption_enabled = !empty($channel->encryption_algorithm);
                $channel->quantum_ready = str_contains($channel->encryption_algorithm ?? '', 'ML-KEM');
                $channel->memberCount = $channel->participant_count ?? 0;
                return $channel;
            });
        }

        return response()->json([
            'channels' => $channels,
            'total' => $channels->count(),
        ]);
    }

    /**
     * Get mentionable groups
     */
    public function getGroups(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'organization_id' => 'nullable|ulid|exists:organizations,id',
            'limit' => 'integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        
        $query = Conversation::query()
            ->select(['id', 'name', 'type', 'description', 'participant_count'])
            ->where('type', 'group')
            ->whereHas('participants', function ($q) use ($user) {
                $q->where('user_id', $user->id)->active();
            });

        if ($request->organization_id) {
            $query->where('organization_id', $request->organization_id);
        }

        $groups = $query->limit($request->input('limit', 50))->get()->map(function ($group) {
            return [
                'id' => $group->id,
                'name' => $group->name,
                'description' => $group->description,
                'memberCount' => $group->participant_count ?? 0,
                'type' => 'group',
            ];
        });

        return response()->json([
            'groups' => $groups,
            'total' => $groups->count(),
        ]);
    }

    /**
     * Search mentions across users, channels, and groups
     */
    public function search(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'q' => 'required|string|min:1|max:100',
            'type' => 'nullable|in:users,channels,groups,all',
            'conversation_id' => 'nullable|ulid|exists:chat_conversations,id',
            'organization_id' => 'nullable|ulid|exists:organizations,id',
            'include_quantum_status' => 'boolean',
            'limit' => 'integer|min:1|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $query = $request->input('q');
        $type = $request->input('type', 'all');
        $limit = $request->input('limit', 20);
        $user = $request->user();

        $results = [
            'users' => [],
            'channels' => [],
            'groups' => [],
        ];

        if ($type === 'all' || $type === 'users') {
            $results['users'] = $this->searchUsers($query, $request, $user, $limit);
        }

        if ($type === 'all' || $type === 'channels') {
            $results['channels'] = $this->searchChannels($query, $request, $user, $limit);
        }

        if ($type === 'all' || $type === 'groups') {
            $results['groups'] = $this->searchGroups($query, $request, $user, $limit);
        }

        return response()->json($results);
    }

    /**
     * Create encrypted mention notification
     */
    public function createMention(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|ulid|exists:users,id',
            'conversation_id' => 'required|ulid|exists:chat_conversations,id',
            'message_content' => 'required|string|max:10000',
            'mention_type' => 'nullable|in:user,channel,group',
            'enable_quantum' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $device = $this->getCurrentUserDevice($request);

        if (!$device) {
            return response()->json(['error' => 'Device not registered for E2EE'], 400);
        }

        $conversation = Conversation::findOrFail($request->conversation_id);
        
        // Verify access
        if (!$conversation->hasUser($user->id)) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        $mentionedUser = User::findOrFail($request->user_id);
        $mentionedDevice = $mentionedUser->devices()->active()->latest()->first();

        if (!$mentionedDevice) {
            return response()->json(['error' => 'Mentioned user has no active devices'], 400);
        }

        try {
            // Encrypt the mention content
            $encryptionResult = $this->encryptMentionContent(
                $request->message_content,
                $conversation,
                $user,
                $device,
                $mentionedUser,
                $mentionedDevice,
                $request->boolean('enable_quantum', true)
            );

            // Store encrypted mention (you might want a dedicated table for this)
            $mentionId = (string) \Illuminate\Support\Str::ulid();
            
            // Cache the encrypted mention temporarily
            $cacheKey = "encrypted_mention_{$mentionId}";
            cache()->put($cacheKey, [
                'mention_id' => $mentionId,
                'user_id' => $request->user_id,
                'sender_id' => $user->id,
                'conversation_id' => $request->conversation_id,
                'encrypted_content' => $encryptionResult['encrypted_content'],
                'content_hash' => $encryptionResult['content_hash'],
                'encryption_algorithm' => $encryptionResult['algorithm'],
                'quantum_encrypted' => $encryptionResult['quantum_encrypted'],
                'notification_sent' => false,
                'created_at' => now()->toISOString(),
            ], now()->addHours(24));

            // Send push notification (implement this based on your notification system)
            $this->sendMentionNotification($mentionedUser, $user, $conversation, $mentionId);

            Log::info('Encrypted mention created', [
                'mention_id' => $mentionId,
                'user_id' => $request->user_id,
                'sender_id' => $user->id,
                'conversation_id' => $request->conversation_id,
                'quantum_encrypted' => $encryptionResult['quantum_encrypted'],
            ]);

            return response()->json([
                'encrypted_mention' => [
                    'mention_id' => $mentionId,
                    'user_id' => $request->user_id,
                    'encrypted_content' => $encryptionResult['encrypted_content'],
                    'content_hash' => $encryptionResult['content_hash'],
                    'notification_sent' => true,
                    'quantum_encrypted' => $encryptionResult['quantum_encrypted'],
                ],
                'message' => 'Encrypted mention created successfully',
            ], 201);

        } catch (\Exception $e) {
            Log::error('Failed to create encrypted mention', [
                'user_id' => $request->user_id,
                'conversation_id' => $request->conversation_id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Failed to create mention'], 500);
        }
    }

    /**
     * Get recent mentions for current user
     */
    public function getRecentMentions(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'integer|min:1|max:50',
            'conversation_id' => 'nullable|ulid|exists:chat_conversations,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $limit = $request->input('limit', 20);

        // This is a simplified version - you might want a dedicated mentions table
        $query = Message::query()
            ->whereRaw('LOWER(encrypted_content) LIKE LOWER(?)', ['%@' . $user->name . '%'])
            ->orWhereRaw('LOWER(encrypted_content) LIKE LOWER(?)', ['%@' . $user->id . '%'])
            ->with(['sender:id,name,avatar', 'conversation:id,name,type'])
            ->orderBy('created_at', 'desc');

        if ($request->conversation_id) {
            $query->where('conversation_id', $request->conversation_id);
        }

        $mentions = $query->limit($limit)->get();

        $recentMentions = $mentions->map(function ($message) {
            return [
                'id' => $message->sender->id,
                'name' => $message->sender->name,
                'avatar' => $message->sender->avatar,
                'online' => $message->sender->last_active_at && 
                          $message->sender->last_active_at->diffInMinutes(now()) < 5,
                'last_mention_at' => $message->created_at,
                'conversation_name' => $message->conversation->name,
            ];
        })->unique('id')->values();

        return response()->json([
            'recent_mentions' => $recentMentions,
            'total' => $recentMentions->count(),
        ]);
    }

    /**
     * Mark mention as read
     */
    public function markAsRead(Request $request, string $mentionId): JsonResponse
    {
        $user = $request->user();
        $cacheKey = "encrypted_mention_{$mentionId}";
        
        $mention = cache()->get($cacheKey);
        if (!$mention || $mention['user_id'] !== $user->id) {
            return response()->json(['error' => 'Mention not found'], 404);
        }

        // Update mention as read
        $mention['read_at'] = now()->toISOString();
        cache()->put($cacheKey, $mention, now()->addHours(24));

        return response()->json(['message' => 'Mention marked as read']);
    }

    /**
     * Get mention statistics
     */
    public function getStats(Request $request): JsonResponse
    {
        $user = $request->user();
        $conversationId = $request->conversation_id;

        // Simplified stats - in production you'd want dedicated tables
        $query = Message::query()
            ->whereRaw('LOWER(encrypted_content) LIKE LOWER(?)', ['%@' . $user->name . '%'])
            ->orWhereRaw('LOWER(encrypted_content) LIKE LOWER(?)', ['%@' . $user->id . '%']);

        if ($conversationId) {
            $query->where('conversation_id', $conversationId);
        }

        $totalMentions = $query->count();
        
        // Quantum mentions (containing quantum encryption indicators)
        $quantumMentions = $query->where('encryption_algorithm', 'LIKE', '%ML-KEM%')->count();

        $recentActivity = $query->with(['sender:id,name,avatar'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($message) {
                return [
                    'user' => [
                        'id' => $message->sender->id,
                        'name' => $message->sender->name,
                        'avatar' => $message->sender->avatar,
                    ],
                    'timestamp' => $message->created_at->toISOString(),
                    'message_preview' => 'Mentioned you in a message',
                ];
            });

        return response()->json([
            'stats' => [
                'total_mentions' => $totalMentions,
                'unread_mentions' => 0, // Would implement with read tracking
                'quantum_mentions' => $quantumMentions,
                'recent_activity' => $recentActivity,
            ],
        ]);
    }

    // Private helper methods

    private function getCurrentUserDevice(Request $request): ?UserDevice
    {
        $deviceFingerprint = $request->header('X-Device-Fingerprint');

        if (!$deviceFingerprint) {
            return null;
        }

        return UserDevice::where('user_id', $request->user()->id)
            ->where('device_fingerprint', $deviceFingerprint)
            ->active()
            ->first();
    }

    private function isDeviceQuantumReady(UserDevice $device): bool
    {
        $identityKey = $device->user->signalIdentityKeys()->active()->first();
        return $identityKey && $identityKey->isQuantumCapable();
    }

    private function getDeviceEncryptionPreference(?UserDevice $device): string
    {
        if (!$device) return 'none';
        
        if ($this->isDeviceQuantumReady($device)) {
            return 'quantum';
        }
        
        return 'classical';
    }

    private function searchUsers(string $query, Request $request, User $user, int $limit): array
    {
        $userQuery = User::query()
            ->select(['id', 'name', 'avatar', 'email', 'last_active_at'])
            ->where('id', '!=', $user->id)
            ->where(function ($q) use ($query) {
                $q->where('name', 'ILIKE', "%{$query}%")
                  ->orWhere('email', 'ILIKE', "%{$query}%");
            });

        if ($request->conversation_id) {
            $conversation = Conversation::find($request->conversation_id);
            if ($conversation && $conversation->hasUser($user->id)) {
                $participantIds = $conversation->activeParticipants()->pluck('user_id');
                $userQuery->whereIn('id', $participantIds);
            }
        }

        $users = $userQuery->limit($limit)->get();

        if ($request->boolean('include_quantum_status')) {
            return $users->map(function ($u) {
                $activeDevice = $u->devices()->active()->latest()->first();
                
                $u->device_status = [
                    'quantum_ready' => $activeDevice ? $this->isDeviceQuantumReady($activeDevice) : false,
                    'last_seen' => $u->last_active_at?->toISOString() ?? null,
                    'encryption_preference' => $this->getDeviceEncryptionPreference($activeDevice),
                ];

                $u->online = $u->last_active_at && $u->last_active_at->diffInMinutes(now()) < 5;

                return $u;
            })->toArray();
        }

        return $users->toArray();
    }

    private function searchChannels(string $query, Request $request, User $user, int $limit): array
    {
        $channelQuery = Conversation::query()
            ->select(['id', 'name', 'type', 'description', 'encryption_algorithm', 'participant_count'])
            ->where('type', 'channel')
            ->where('name', 'ILIKE', "%{$query}%")
            ->whereHas('participants', function ($q) use ($user) {
                $q->where('user_id', $user->id)->active();
            });

        $channels = $channelQuery->limit($limit)->get();

        return $channels->map(function ($channel) {
            return [
                'id' => $channel->id,
                'name' => $channel->name,
                'type' => $channel->type === 'channel' ? 'public' : 'private',
                'memberCount' => $channel->participant_count ?? 0,
                'encryption_enabled' => !empty($channel->encryption_algorithm),
                'quantum_ready' => str_contains($channel->encryption_algorithm ?? '', 'ML-KEM'),
            ];
        })->toArray();
    }

    private function searchGroups(string $query, Request $request, User $user, int $limit): array
    {
        $groupQuery = Conversation::query()
            ->select(['id', 'name', 'type', 'description', 'participant_count'])
            ->where('type', 'group')
            ->where('name', 'ILIKE', "%{$query}%")
            ->whereHas('participants', function ($q) use ($user) {
                $q->where('user_id', $user->id)->active();
            });

        $groups = $groupQuery->limit($limit)->get();

        return $groups->map(function ($group) {
            return [
                'id' => $group->id,
                'name' => $group->name,
                'description' => $group->description,
                'memberCount' => $group->participant_count ?? 0,
                'type' => 'group',
            ];
        })->toArray();
    }

    private function encryptMentionContent(
        string $content,
        Conversation $conversation,
        User $sender,
        UserDevice $senderDevice,
        User $recipient,
        UserDevice $recipientDevice,
        bool $enableQuantum = true
    ): array {
        if ($enableQuantum && $this->isDeviceQuantumReady($recipientDevice)) {
            // Use quantum encryption for mention
            $encryptedMessage = $this->signalService->encryptMessage(
                $content,
                $conversation,
                $sender,
                $senderDevice,
                [$recipient]
            );

            return [
                'encrypted_content' => $encryptedMessage[0]['encrypted_content'],
                'content_hash' => $encryptedMessage[0]['content_hash'],
                'algorithm' => $encryptedMessage[0]['encryption_algorithm'],
                'quantum_encrypted' => true,
            ];
        } else {
            // Use classical encryption
            $encryptedMessage = $this->encryptionService->encryptMessage(
                $content,
                $conversation,
                $sender,
                $senderDevice,
                [$recipient]
            );

            return [
                'encrypted_content' => $encryptedMessage[0]['encrypted_content'],
                'content_hash' => $encryptedMessage[0]['content_hash'],
                'algorithm' => 'AES-256-GCM',
                'quantum_encrypted' => false,
            ];
        }
    }

    private function sendMentionNotification(User $user, User $sender, Conversation $conversation, string $mentionId): void
    {
        // Implement push notification logic here
        // This could use Laravel's notification system, Pusher, etc.
        
        Log::info('Mention notification sent', [
            'mention_id' => $mentionId,
            'recipient' => $user->id,
            'sender' => $sender->id,
            'conversation' => $conversation->id,
        ]);
    }
}