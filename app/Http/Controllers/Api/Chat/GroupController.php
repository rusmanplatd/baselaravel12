<?php

namespace App\Http\Controllers\Api\Chat;

use App\Http\Controllers\Controller;
use App\Models\Chat\Conversation;
use App\Models\User;
use App\Services\GroupEncryptionService;
use App\Services\SignalProtocolService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class GroupController extends Controller
{
    public function __construct(
        private SignalProtocolService $signalService,
        private GroupEncryptionService $groupEncryptionService
    ) {
        $this->middleware('auth:api');
        $this->middleware('throttle:30,1')->only(['create', 'join']);
        $this->middleware('throttle:60,1')->only(['updateRole', 'kick']);
    }

    /**
     * Create a new group or channel
     */
    public function create(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'type' => 'required|in:group,channel',
            'privacy' => 'required|in:private,public,secret',
            'participants' => 'required|array|min:1|max:500',
            'participants.*' => 'exists:sys_users,id',
            'group_settings' => 'nullable|array',
            'encryption_mode' => 'required|in:standard,quantum,hybrid',
            'max_participants' => 'nullable|integer|min:2|max:1000',
            'require_approval' => 'boolean',
            'allow_message_history' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();

        try {
            DB::beginTransaction();

            // Create the group/channel
            $conversation = Conversation::create([
                'type' => $request->type,
                'name' => $request->name,
                'description' => $request->description,
                'created_by' => $user->id,
                'settings' => array_merge($request->input('group_settings', []), [
                    'privacy' => $request->privacy,
                    'max_participants' => $request->input('max_participants', 100),
                    'require_approval' => $request->boolean('require_approval'),
                    'allow_message_history' => $request->boolean('allow_message_history', true),
                    'encryption_mode' => $request->encryption_mode,
                ]),
                'encryption_algorithm' => $this->getEncryptionAlgorithm($request->encryption_mode),
                'status' => 'active',
            ]);

            // Add creator as admin
            $conversation->addParticipant($user->id, ['role' => 'admin']);

            // Add other participants
            $participantUsers = User::whereIn('id', $request->participants)
                ->where('id', '!=', $user->id)
                ->get();

            foreach ($participantUsers as $participant) {
                $role = $request->type === 'channel' ? 'member' : 'member';
                $conversation->addParticipant($participant->id, ['role' => $role]);
            }

            // Initialize group encryption
            $this->groupEncryptionService->initializeGroupEncryption(
                $conversation,
                $user,
                $request->encryption_mode
            );

            // Create initial system message
            $this->createSystemMessage($conversation, $user, [
                'type' => 'group_created',
                'group_name' => $conversation->name,
                'creator_id' => $user->id,
                'participant_count' => $participantUsers->count() + 1,
            ]);

            DB::commit();

            $conversation->load([
                'participants.user:id,name,avatar',
                'encryptionKeys' => function ($query) use ($user) {
                    $query->where('user_id', $user->id)->active();
                },
            ]);

            Log::info('Group/Channel created', [
                'conversation_id' => $conversation->id,
                'type' => $conversation->type,
                'creator_id' => $user->id,
                'participant_count' => $participantUsers->count() + 1,
                'encryption_mode' => $request->encryption_mode,
            ]);

            return response()->json([
                'conversation' => $conversation,
                'message' => ucfirst($request->type).' created successfully',
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create group/channel', [
                'user_id' => $user->id,
                'type' => $request->type,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Failed to create '.$request->type], 500);
        }
    }

    /**
     * Join a public group or channel
     */
    public function join(Request $request, string $conversationId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'join_code' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $conversation = Conversation::findOrFail($conversationId);

        // Check if group is joinable
        if (! $this->canJoinGroup($conversation, $user, $request->join_code)) {
            return response()->json(['error' => 'Cannot join this group'], 403);
        }

        // Check if already a participant
        if ($conversation->hasUser($user->id)) {
            return response()->json(['error' => 'Already a member of this group'], 409);
        }

        // Check participant limit
        $settings = $conversation->settings ?? [];
        $maxParticipants = $settings['max_participants'] ?? 100;

        if ($conversation->getParticipantCount() >= $maxParticipants) {
            return response()->json(['error' => 'Group is full'], 409);
        }

        try {
            DB::beginTransaction();

            // Add as participant
            $conversation->addParticipant($user->id, [
                'role' => 'member',
                'joined_via' => $request->join_code ? 'invite_code' : 'direct',
            ]);

            // Generate encryption keys for new participant
            $this->groupEncryptionService->generateKeysForNewParticipant($conversation, $user);

            // Create system message
            $this->createSystemMessage($conversation, $user, [
                'type' => 'participant_joined',
                'user_id' => $user->id,
                'joined_via' => $request->join_code ? 'invite_code' : 'direct',
            ]);

            DB::commit();

            Log::info('User joined group', [
                'conversation_id' => $conversation->id,
                'user_id' => $user->id,
                'join_method' => $request->join_code ? 'invite_code' : 'direct',
            ]);

            return response()->json([
                'message' => 'Successfully joined the group',
                'conversation' => $conversation->load('participants.user:id,name,avatar'),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to join group', [
                'conversation_id' => $conversationId,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Failed to join group'], 500);
        }
    }

    /**
     * Update participant role in group
     */
    public function updateRole(Request $request, string $conversationId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:sys_users,id',
            'role' => 'required|in:admin,moderator,member',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $conversation = Conversation::findOrFail($conversationId);
        $targetUser = User::findOrFail($request->user_id);

        // Check permissions
        $userParticipant = $conversation->participants()->where('user_id', $user->id)->active()->first();
        if (! $userParticipant || ! $userParticipant->canManageRoles()) {
            return response()->json(['error' => 'Insufficient permissions'], 403);
        }

        $targetParticipant = $conversation->participants()->where('user_id', $targetUser->id)->active()->first();
        if (! $targetParticipant) {
            return response()->json(['error' => 'User is not a participant'], 404);
        }

        // Prevent self-demotion if user is the only admin
        if ($user->id === $targetUser->id && $request->role !== 'admin') {
            $adminCount = $conversation->participants()->where('role', 'admin')->active()->count();
            if ($adminCount <= 1) {
                return response()->json(['error' => 'Cannot demote the last admin'], 400);
            }
        }

        try {
            $oldRole = $targetParticipant->role;
            $targetParticipant->update(['role' => $request->role]);

            // Create system message
            $this->createSystemMessage($conversation, $user, [
                'type' => 'role_updated',
                'target_user_id' => $targetUser->id,
                'old_role' => $oldRole,
                'new_role' => $request->role,
                'updated_by' => $user->id,
            ]);

            Log::info('Participant role updated', [
                'conversation_id' => $conversation->id,
                'target_user_id' => $targetUser->id,
                'old_role' => $oldRole,
                'new_role' => $request->role,
                'updated_by' => $user->id,
            ]);

            return response()->json([
                'message' => 'Role updated successfully',
                'participant' => $targetParticipant->load('user:id,name,avatar'),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update participant role', [
                'conversation_id' => $conversationId,
                'target_user_id' => $targetUser->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Failed to update role'], 500);
        }
    }

    /**
     * Kick participant from group
     */
    public function kick(Request $request, string $conversationId): JsonResponse
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
        $targetUser = User::findOrFail($request->user_id);

        // Check permissions
        $userParticipant = $conversation->participants()->where('user_id', $user->id)->active()->first();
        if (! $userParticipant || ! $userParticipant->canRemoveMembers()) {
            return response()->json(['error' => 'Insufficient permissions'], 403);
        }

        // Cannot kick yourself
        if ($user->id === $targetUser->id) {
            return response()->json(['error' => 'Cannot kick yourself'], 400);
        }

        // Cannot kick other admins (unless you are creator)
        $targetParticipant = $conversation->participants()->where('user_id', $targetUser->id)->active()->first();
        if (! $targetParticipant) {
            return response()->json(['error' => 'User is not a participant'], 404);
        }

        if ($targetParticipant->role === 'admin' && $conversation->created_by !== $user->id) {
            return response()->json(['error' => 'Cannot kick another admin'], 403);
        }

        try {
            DB::beginTransaction();

            // Remove participant
            $this->signalService->removeParticipantFromConversation(
                $conversation,
                $targetUser,
                $user,
                $request->reason ?? 'Kicked from group'
            );

            // Create system message
            $this->createSystemMessage($conversation, $user, [
                'type' => 'participant_kicked',
                'target_user_id' => $targetUser->id,
                'kicked_by' => $user->id,
                'reason' => $request->reason,
            ]);

            DB::commit();

            Log::info('Participant kicked from group', [
                'conversation_id' => $conversation->id,
                'target_user_id' => $targetUser->id,
                'kicked_by' => $user->id,
                'reason' => $request->reason,
            ]);

            return response()->json(['message' => 'Participant kicked successfully']);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to kick participant', [
                'conversation_id' => $conversationId,
                'target_user_id' => $targetUser->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Failed to kick participant'], 500);
        }
    }

    /**
     * Generate invite code for group
     */
    public function generateInviteCode(Request $request, string $conversationId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'expires_in_hours' => 'nullable|integer|min:1|max:168', // Max 7 days
            'max_uses' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $conversation = Conversation::findOrFail($conversationId);

        // Check permissions
        $participant = $conversation->participants()->where('user_id', $user->id)->active()->first();
        if (! $participant || ! $participant->canAddMembers()) {
            return response()->json(['error' => 'Insufficient permissions'], 403);
        }

        try {
            $inviteCode = $this->generateUniqueInviteCode();
            $expiresAt = $request->expires_in_hours
                ? now()->addHours($request->expires_in_hours)
                : now()->addDays(7);

            // Store invite code (you might want to create a separate table for this)
            $inviteData = [
                'conversation_id' => $conversation->id,
                'created_by' => $user->id,
                'code' => $inviteCode,
                'expires_at' => $expiresAt,
                'max_uses' => $request->input('max_uses', 10),
                'current_uses' => 0,
            ];

            cache()->put("group_invite_{$inviteCode}", $inviteData, $expiresAt);

            Log::info('Group invite code generated', [
                'conversation_id' => $conversation->id,
                'created_by' => $user->id,
                'expires_at' => $expiresAt,
            ]);

            return response()->json([
                'invite_code' => $inviteCode,
                'expires_at' => $expiresAt,
                'max_uses' => $inviteData['max_uses'],
                'invite_url' => url("/chat/join/{$inviteCode}"),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to generate invite code', [
                'conversation_id' => $conversationId,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Failed to generate invite code'], 500);
        }
    }

    /**
     * Get group analytics (for admins)
     */
    public function analytics(Request $request, string $conversationId): JsonResponse
    {
        $user = $request->user();
        $conversation = Conversation::with('participants.user')->findOrFail($conversationId);

        // Check permissions
        $participant = $conversation->participants()->where('user_id', $user->id)->active()->first();
        if (! $participant || ! $participant->isModerator()) {
            return response()->json(['error' => 'Insufficient permissions'], 403);
        }

        try {
            $analytics = [
                'participant_stats' => [
                    'total' => $conversation->participants()->active()->count(),
                    'by_role' => $conversation->participants()
                        ->active()
                        ->selectRaw('role, COUNT(*) as count')
                        ->groupBy('role')
                        ->pluck('count', 'role'),
                    'recent_joins' => $conversation->participants()
                        ->active()
                        ->where('joined_at', '>=', now()->subDays(7))
                        ->count(),
                ],
                'message_stats' => [
                    'total' => $conversation->messages()->count(),
                    'today' => $conversation->messages()
                        ->where('created_at', '>=', now()->startOfDay())
                        ->count(),
                    'this_week' => $conversation->messages()
                        ->where('created_at', '>=', now()->startOfWeek())
                        ->count(),
                    'top_contributors' => $conversation->messages()
                        ->selectRaw('sender_id, COUNT(*) as message_count')
                        ->where('created_at', '>=', now()->subDays(30))
                        ->groupBy('sender_id')
                        ->orderByDesc('message_count')
                        ->limit(10)
                        ->with('sender:id,name,avatar')
                        ->get(),
                ],
                'encryption_stats' => [
                    'algorithm' => $conversation->encryption_algorithm,
                    'quantum_ready_participants' => $conversation->participants()
                        ->whereHas('user.devices', function ($query) {
                            $query->where('quantum_ready', true)->active();
                        })
                        ->count(),
                    'active_encryption_keys' => $conversation->encryptionKeys()
                        ->active()
                        ->count(),
                ],
                'activity_timeline' => $this->getActivityTimeline($conversation),
            ];

            return response()->json(['analytics' => $analytics]);

        } catch (\Exception $e) {
            Log::error('Failed to get group analytics', [
                'conversation_id' => $conversationId,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Failed to get analytics'], 500);
        }
    }

    /**
     * Archive/unarchive group
     */
    public function archive(Request $request, string $conversationId): JsonResponse
    {
        $user = $request->user();
        $conversation = Conversation::findOrFail($conversationId);

        // Check permissions (only admins can archive)
        $participant = $conversation->participants()->where('user_id', $user->id)->active()->first();
        if (! $participant || ! $participant->isAdmin()) {
            return response()->json(['error' => 'Insufficient permissions'], 403);
        }

        try {
            $isArchiving = $conversation->status === 'active';
            $conversation->update([
                'status' => $isArchiving ? 'archived' : 'active',
            ]);

            // Create system message
            $this->createSystemMessage($conversation, $user, [
                'type' => $isArchiving ? 'group_archived' : 'group_unarchived',
                'archived_by' => $user->id,
            ]);

            Log::info('Group archive status changed', [
                'conversation_id' => $conversation->id,
                'user_id' => $user->id,
                'action' => $isArchiving ? 'archived' : 'unarchived',
            ]);

            return response()->json([
                'message' => 'Group '.($isArchiving ? 'archived' : 'unarchived').' successfully',
                'status' => $conversation->status,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to change group archive status', [
                'conversation_id' => $conversationId,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Failed to update group status'], 500);
        }
    }

    /**
     * Check if user can join a group
     */
    private function canJoinGroup(Conversation $conversation, User $user, ?string $joinCode): bool
    {
        $settings = $conversation->settings ?? [];
        $privacy = $settings['privacy'] ?? 'private';

        switch ($privacy) {
            case 'public':
                return true;

            case 'private':
                // Need invite code or admin approval
                if ($joinCode) {
                    $inviteData = cache()->get("group_invite_{$joinCode}");

                    return $inviteData &&
                           $inviteData['conversation_id'] === $conversation->id &&
                           $inviteData['current_uses'] < $inviteData['max_uses'];
                }

                return false;

            case 'secret':
                // Only by invite code
                if ($joinCode) {
                    $inviteData = cache()->get("group_invite_{$joinCode}");

                    return $inviteData &&
                           $inviteData['conversation_id'] === $conversation->id &&
                           $inviteData['current_uses'] < $inviteData['max_uses'];
                }

                return false;

            default:
                return false;
        }
    }

    /**
     * Get encryption algorithm based on mode
     */
    private function getEncryptionAlgorithm(string $mode): string
    {
        return match ($mode) {
            'quantum' => 'ML-KEM-768',
            'hybrid' => 'HYBRID-ML-KEM-768',
            default => 'AES-256-GCM',
        };
    }

    /**
     * Generate unique invite code
     */
    private function generateUniqueInviteCode(): string
    {
        do {
            $code = strtoupper(substr(md5(uniqid()), 0, 8));
        } while (cache()->has("group_invite_{$code}"));

        return $code;
    }

    /**
     * Create system message
     */
    private function createSystemMessage(Conversation $conversation, User $user, array $data): void
    {
        $conversation->messages()->create([
            'sender_id' => $user->id,
            'type' => 'system',
            'encrypted_content' => encrypt(json_encode($data)),
            'content_hash' => hash('sha256', json_encode($data)),
            'status' => 'sent',
        ]);
    }

    /**
     * Get activity timeline for analytics
     */
    private function getActivityTimeline(Conversation $conversation): array
    {
        // Get activity data for the last 30 days
        $timeline = [];

        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i)->startOfDay();

            $timeline[] = [
                'date' => $date->format('Y-m-d'),
                'messages' => $conversation->messages()
                    ->whereBetween('created_at', [$date, $date->copy()->endOfDay()])
                    ->count(),
                'joins' => $conversation->participants()
                    ->whereBetween('joined_at', [$date, $date->copy()->endOfDay()])
                    ->count(),
            ];
        }

        return $timeline;
    }
}
