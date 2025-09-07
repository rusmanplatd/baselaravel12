<?php

namespace App\Http\Controllers\Api\Chat;

use App\Http\Controllers\Controller;
use App\Models\Chat\Conversation;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\SignalProtocolService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ConversationController extends Controller
{
    public function __construct(
        private SignalProtocolService $signalService
    ) {
        $this->middleware('auth:api');
        $this->middleware('throttle:60,1')->only(['store']);
        $this->middleware('throttle:30,1')->only(['addParticipant', 'removeParticipant']);
        
        // Apply chat permissions - using standard chat permissions that all users have
        $this->middleware('chat.permission:chat:write')->only(['store']);
        $this->middleware('chat.permission:chat:manage,conversationId')->only([
            'addParticipant', 'removeParticipant', 'update'
        ]);
        $this->middleware('chat.permission:chat:manage,conversationId')->only(['rotateKeys']);
    }

    /**
     * Get user's conversations with pagination
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $page = $request->input('page', 1);
        $limit = min($request->input('limit', 20), 50);

        $conversations = Conversation::forUser($user->id)
            ->with([
                'latestMessage' => function ($query) {
                    $query->select('id', 'conversation_id', 'sender_id', 'type', 'status', 'created_at');
                },
                'latestMessage.sender:id,name,avatar',
                'participants' => function ($query) {
                    $query->active()->with('user:id,name,avatar')->limit(10);
                },
            ])
            ->withCount(['participants as participant_count' => function ($query) {
                $query->active();
            }])
            ->orderByDesc('last_activity_at')
            ->paginate($limit, ['*'], 'page', $page);

        // Add unread counts and encryption status
        $conversations->getCollection()->transform(function ($conversation) use ($user) {
            $participant = $conversation->participants->firstWhere('user_id', $user->id);
            $conversation->unread_count = $participant ? $participant->getUnreadCount() : 0;
            $conversation->is_muted = $participant ? $participant->isMuted() : false;
            $conversation->encryption_status = $this->getEncryptionStatus($conversation, $user);

            // Remove sensitive data
            unset($conversation->encryption_info);

            return $conversation;
        });

        return response()->json([
            'conversations' => $conversations->items(),
            'pagination' => [
                'current_page' => $conversations->currentPage(),
                'last_page' => $conversations->lastPage(),
                'per_page' => $conversations->perPage(),
                'total' => $conversations->total(),
            ],
        ]);
    }

    /**
     * Get specific conversation details
     */
    public function show(Request $request, string $conversationId): JsonResponse
    {
        $user = $request->user();

        $conversation = Conversation::with([
            'participants.user:id,name,avatar,last_seen_at',
            'encryptionKeys' => function ($query) use ($user) {
                $query->where('user_id', $user->id)->active();
            },
        ])->findOrFail($conversationId);

        // Check if user is participant
        if (! $conversation->hasUser($user->id)) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        // Get user's participant record
        $participant = $conversation->participants->firstWhere('user_id', $user->id);

        $conversation->encryption_status = $this->getEncryptionStatus($conversation, $user);
        $conversation->user_role = $participant?->role;
        $conversation->user_permissions = $participant?->permissions ?? [];
        $conversation->unread_count = $participant?->getUnreadCount() ?? 0;
        $conversation->is_muted = $participant?->isMuted() ?? false;

        // Remove sensitive encryption data
        unset($conversation->encryption_info);

        return response()->json(['conversation' => $conversation]);
    }

    /**
     * Create a new conversation
     */
    public function store(Request $request): JsonResponse
    {
        $type = $request->input('type');
        
        // Dynamic validation based on conversation type
        $validationRules = [
            'type' => 'required|in:direct,group,channel',
            'participants' => 'required|array|min:1',
            'participants.*' => 'exists:sys_users,id',
            'enable_quantum' => 'boolean',
            'key_strength' => 'integer|in:512,768,1024',
            'avatar_url' => 'nullable|url|max:255',
            'settings' => 'nullable|array',
        ];

        // Type-specific validation rules
        if ($type === 'direct') {
            $validationRules['participants'] = 'required|array|min:1|max:2'; // 1-2 participants (initiator auto-included if not present)
            $validationRules['name'] = 'nullable|string|max:255';
            $validationRules['description'] = 'nullable|string|max:500';
        } elseif ($type === 'group') {
            $validationRules['participants'] = 'required|array|min:2|max:100'; // 2-100 participants for groups
            $validationRules['name'] = 'required|string|min:1|max:255'; // Name required for groups
            $validationRules['description'] = 'nullable|string|max:1000';
        } elseif ($type === 'channel') {
            $validationRules['participants'] = 'required|array|min:1|max:500'; // Up to 500 for channels
            $validationRules['name'] = 'required|string|min:1|max:255'; // Name required for channels
            $validationRules['description'] = 'nullable|string|max:2000'; // Longer description for channels
            $validationRules['settings.is_public'] = 'nullable|boolean';
            $validationRules['settings.allow_member_invites'] = 'nullable|boolean';
            $validationRules['settings.moderated'] = 'nullable|boolean';
        }

        $validator = Validator::make($request->all(), $validationRules);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $device = $this->getCurrentUserDevice($request);

        if (! $device) {
            return response()->json(['error' => 'Device not registered for E2EE'], 400);
        }

        try {
            DB::beginTransaction();

            // Get participant users
            $participantUsers = User::whereIn('id', $request->participants)->get();
            
            // Check if all requested participants exist
            if ($participantUsers->count() !== count($request->participants)) {
                return response()->json(['error' => 'Some participants not found'], 404);
            }

            // Ensure initiator is included
            if (! $participantUsers->pluck('id')->contains($user->id)) {
                $participantUsers->push($user);
            }

            // Validate direct message constraints
            if ($type === 'direct') {
                if ($participantUsers->count() !== 2) {
                    return response()->json(['error' => 'Direct conversations must have exactly 2 participants'], 422);
                }
                
                // Prevent creating conversation with yourself
                $userIds = $participantUsers->pluck('id')->toArray();
                if (count(array_unique($userIds)) < 2) {
                    return response()->json(['error' => 'Cannot create direct conversation with yourself'], 422);
                }
            }

            // For direct messages, check if conversation already exists
            if ($type === 'direct') {
                $existingConversation = $this->findExistingDirectMessage($participantUsers->pluck('id')->toArray());
                if ($existingConversation) {
                    DB::commit();
                    return response()->json([
                        'conversation' => $existingConversation,
                        'message' => 'Direct conversation already exists',
                        'existing' => true,
                    ], 200);
                }
            }

            // Prepare conversation options based on type
            $conversationOptions = [
                'type' => $type,
                'name' => $request->name,
                'description' => $request->description,
                'avatar_url' => $request->avatar_url,
                'enable_quantum' => $request->boolean('enable_quantum'),
                'key_strength' => $request->input('key_strength', 768),
                'settings' => $request->input('settings', []),
            ];

            // Add type-specific settings
            if ($type === 'channel') {
                $conversationOptions['settings'] = array_merge([
                    'is_public' => false,
                    'allow_member_invites' => true,
                    'moderated' => false,
                ], $conversationOptions['settings']);
            } elseif ($type === 'group') {
                $conversationOptions['settings'] = array_merge([
                    'allow_member_invites' => true,
                    'everyone_can_add_members' => true,
                ], $conversationOptions['settings']);
            }

            // Create conversation with E2EE
            $conversation = $this->signalService->startConversation(
                $user,
                $device,
                $participantUsers->all(),
                $conversationOptions
            );

            // Set appropriate roles based on conversation type
            $this->setupConversationRoles($conversation, $user, $type);

            DB::commit();

            // Load relationships for response
            $conversation->load([
                'participants.user:id,name,avatar',
                'encryptionKeys' => function ($query) use ($user) {
                    $query->where('user_id', $user->id)->active();
                },
            ]);

            $conversation->encryption_status = $this->getEncryptionStatus($conversation, $user);

            Log::info('New conversation created', [
                'conversation_id' => $conversation->id,
                'creator_id' => $user->id,
                'type' => $conversation->type,
                'participant_count' => $participantUsers->count(),
                'has_name' => !empty($conversation->name),
            ]);

            return response()->json([
                'conversation' => $conversation,
                'message' => ucfirst($type) . ' conversation created successfully',
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create conversation', [
                'user_id' => $user->id,
                'type' => $type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'Failed to create ' . $type . ' conversation'], 500);
        }
    }

    /**
     * Add participant to conversation
     */
    public function addParticipant(Request $request, string $conversationId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:sys_users,id',
            'role' => 'nullable|in:admin,moderator,member',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $conversation = Conversation::findOrFail($conversationId);
        $newParticipant = User::findOrFail($request->user_id);

        try {
            $this->signalService->addParticipantToConversation(
                $conversation,
                $newParticipant,
                $user,
                ['role' => $request->input('role', 'member')]
            );

            return response()->json([
                'message' => 'Participant added successfully',
                'participant' => $newParticipant->only(['id', 'name', 'avatar']),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to add participant', [
                'conversation_id' => $conversationId,
                'user_id' => $user->id,
                'new_participant_id' => $newParticipant->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => $e->getMessage()], 403);
        }
    }

    /**
     * Remove participant from conversation
     */
    public function removeParticipant(Request $request, string $conversationId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:sys_users,id',
            'reason' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $conversation = Conversation::findOrFail($conversationId);
        $participantToRemove = User::findOrFail($request->user_id);

        try {
            $this->signalService->removeParticipantFromConversation(
                $conversation,
                $participantToRemove,
                $user,
                $request->reason
            );

            return response()->json([
                'message' => 'Participant removed successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to remove participant', [
                'conversation_id' => $conversationId,
                'user_id' => $user->id,
                'participant_to_remove_id' => $participantToRemove->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => $e->getMessage()], 403);
        }
    }

    /**
     * Leave conversation
     */
    public function leave(Request $request, string $conversationId): JsonResponse
    {
        $user = $request->user();
        $conversation = Conversation::findOrFail($conversationId);

        if (! $conversation->hasUser($user->id)) {
            return response()->json(['error' => 'You are not a participant in this conversation'], 403);
        }

        try {
            $participant = $conversation->participants()->where('user_id', $user->id)->first();
            $participant->leave();

            // If it was a direct message or user was the last participant, mark conversation as inactive
            if ($conversation->isDirectMessage() || $conversation->getParticipantCount() <= 1) {
                $conversation->update(['status' => 'inactive']);
            }

            return response()->json(['message' => 'Left conversation successfully']);

        } catch (\Exception $e) {
            Log::error('Failed to leave conversation', [
                'conversation_id' => $conversationId,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Failed to leave conversation'], 500);
        }
    }

    /**
     * Rotate conversation encryption keys
     */
    public function rotateKeys(Request $request, string $conversationId): JsonResponse
    {
        $user = $request->user();
        $conversation = Conversation::findOrFail($conversationId);

        // Check if user has permission to rotate keys
        $participant = $conversation->participants()->where('user_id', $user->id)->active()->first();
        if (! $participant || ! $participant->isModerator()) {
            return response()->json(['error' => 'Insufficient permissions'], 403);
        }

        try {
            $this->signalService->rotateConversationKeys($conversation, $user);

            return response()->json([
                'message' => 'Encryption keys rotated successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to rotate conversation keys', [
                'conversation_id' => $conversationId,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Failed to rotate keys'], 500);
        }
    }

    /**
     * Update conversation settings
     */
    public function update(Request $request, string $conversationId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'avatar_url' => 'nullable|url',
            'settings' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $conversation = Conversation::findOrFail($conversationId);

        // Check permissions
        $participant = $conversation->participants()->where('user_id', $user->id)->active()->first();
        if (! $participant || ! $participant->isModerator()) {
            return response()->json(['error' => 'Insufficient permissions'], 403);
        }

        try {
            $conversation->update($request->only(['name', 'description', 'avatar_url', 'settings']));

            return response()->json([
                'conversation' => $conversation,
                'message' => 'Conversation updated successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update conversation', [
                'conversation_id' => $conversationId,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Failed to update conversation'], 500);
        }
    }

    /**
     * Get current user device from request
     */
    private function getCurrentUserDevice(Request $request): ?UserDevice
    {
        $deviceFingerprint = $request->header('X-Device-Fingerprint');

        if (! $deviceFingerprint) {
            return null;
        }

        return UserDevice::where('user_id', $request->user()->id)
            ->where('device_fingerprint', $deviceFingerprint)
            ->active()
            ->first();
    }

    /**
     * Find existing direct message conversation between users
     */
    private function findExistingDirectMessage(array $userIds): ?Conversation
    {
        if (count($userIds) !== 2) {
            return null;
        }

        return Conversation::whereType('direct')
            ->whereHas('participants', function ($query) use ($userIds) {
                $query->where('user_id', $userIds[0])->active();
            })
            ->whereHas('participants', function ($query) use ($userIds) {
                $query->where('user_id', $userIds[1])->active();
            })
            ->whereHas('participants', function ($query) {
                $query->active();
            }, '=', 2) // Exactly 2 participants
            ->with([
                'participants.user:id,name,avatar',
                'latestMessage:id,conversation_id,sender_id,type,status,created_at'
            ])
            ->first();
    }

    /**
     * Setup conversation roles based on type
     */
    private function setupConversationRoles(Conversation $conversation, User $creator, string $type): void
    {
        if ($type === 'direct') {
            // In direct messages, all participants are equal (members)
            return;
        }

        // For groups and channels, set creator as admin
        $creatorParticipant = $conversation->participants()
            ->where('user_id', $creator->id)
            ->first();

        if ($creatorParticipant) {
            $creatorParticipant->update([
                'role' => 'admin',
                'permissions' => [
                    'send_messages',
                    'delete_messages',
                    'add_members',
                    'remove_members',
                    'manage_roles',
                    'manage_settings',
                    'pin_messages',
                ]
            ]);
        }

        // Set other participants as members with basic permissions
        $otherParticipants = $conversation->participants()
            ->where('user_id', '!=', $creator->id)
            ->get();

        foreach ($otherParticipants as $participant) {
            $permissions = ['send_messages'];
            
            // Add additional permissions based on conversation type
            if ($type === 'group') {
                $permissions[] = 'add_members'; // Groups allow members to add others by default
            }

            $participant->update([
                'role' => 'member',
                'permissions' => $permissions
            ]);
        }
    }

    /**
     * Get encryption status for conversation
     */
    private function getEncryptionStatus(Conversation $conversation, User $user): array
    {
        $activeKeysQuery = $conversation->encryptionKeys()
            ->where('user_id', $user->id);
        
        // Check if the active() scope exists on the relationship
        $activeKeys = $activeKeysQuery->where('is_active', true)->count();

        return [
            'is_encrypted' => $conversation->isEncrypted(),
            'algorithm' => $conversation->settings['encryption_algorithm'] ?? 'RSA-4096-OAEP',
            'key_strength' => $conversation->settings['key_strength'] ?? 768,
            'active_keys' => $activeKeys,
            'quantum_ready' => str_contains($conversation->settings['encryption_algorithm'] ?? '', 'ML-KEM'),
        ];
    }
}
