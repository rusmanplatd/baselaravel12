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
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:direct,group,channel',
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'participants' => 'required|array|min:1|max:100',
            'participants.*' => 'exists:sys_users,id',
            'enable_quantum' => 'boolean',
            'key_strength' => 'integer|in:256,512,1024',
        ]);

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

            // Ensure initiator is included
            if (! $participantUsers->pluck('id')->contains($user->id)) {
                $participantUsers->push($user);
            }

            // Create conversation with E2EE
            $conversation = $this->signalService->startConversation(
                $user,
                $device,
                $participantUsers->toArray(),
                [
                    'name' => $request->name,
                    'description' => $request->description,
                    'enable_quantum' => $request->boolean('enable_quantum'),
                    'key_strength' => $request->input('key_strength', 256),
                ]
            );

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
            ]);

            return response()->json([
                'conversation' => $conversation,
                'message' => 'Conversation created successfully',
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create conversation', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Failed to create conversation'], 500);
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
     * Get encryption status for conversation
     */
    private function getEncryptionStatus(Conversation $conversation, User $user): array
    {
        $activeKeys = $conversation->encryptionKeys()
            ->where('user_id', $user->id)
            ->active()
            ->count();

        return [
            'is_encrypted' => $conversation->isEncrypted(),
            'algorithm' => $conversation->encryption_algorithm,
            'key_strength' => $conversation->key_strength,
            'active_keys' => $activeKeys,
            'quantum_ready' => str_contains($conversation->encryption_algorithm, 'ML-KEM'),
        ];
    }
}
