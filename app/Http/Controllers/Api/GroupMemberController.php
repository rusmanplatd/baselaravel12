<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chat\Conversation;
use App\Models\Chat\Participant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GroupMemberController extends Controller
{
    public function index(string $groupId, Request $request): JsonResponse
    {
        $group = Conversation::groups()->findOrFail($groupId);
        $user = Auth::user();

        // Check if user can view members
        if (!$this->canUserViewMembers($group, $user?->id)) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $query = $group->activeParticipants()
            ->with(['user:id,name,email,avatar_url', 'lastReadMessage'])
            ->orderBy('role', 'asc') // admins first
            ->orderBy('joined_at', 'asc');

        // Filter by role
        if ($request->filled('role')) {
            $query->byRole($request->role);
        }

        // Search by name
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'ILIKE', "%{$search}%")
                    ->orWhere('email', 'ILIKE', "%{$search}%");
            });
        }

        $members = $query->paginate($request->per_page ?? 50);

        return response()->json($members);
    }

    public function show(string $groupId, string $memberId): JsonResponse
    {
        $group = Conversation::groups()->findOrFail($groupId);
        $user = Auth::user();

        if (!$this->canUserViewMembers($group, $user?->id)) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $member = $group->activeParticipants()
            ->with(['user', 'lastReadMessage', 'conversation'])
            ->findOrFail($memberId);

        $memberData = [
            'member' => $member,
            'join_date' => $member->joined_at,
            'message_count' => $group->messages()->where('user_id', $member->user_id)->count(),
            'last_activity' => $member->last_read_at,
            'restrictions' => $group->memberRestrictions()
                ->where('participant_id', $member->id)
                ->where('is_active', true)
                ->get(),
        ];

        return response()->json($memberData);
    }

    public function addMember(Request $request, string $groupId): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:sys_users,id',
            'role' => 'sometimes|in:admin,moderator,member',
            'permissions' => 'sometimes|array',
        ]);

        $group = Conversation::groups()->findOrFail($groupId);
        $user = Auth::user();
        $targetUserId = $request->user_id;

        // Check permissions
        if (!$this->canUserAddMembers($group, $user->id)) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        // Check if user is already a member
        if ($group->hasUser($targetUserId)) {
            return response()->json(['message' => 'User is already a member'], 400);
        }

        // Check if user is banned
        if ($group->isUserBanned($targetUserId)) {
            return response()->json(['message' => 'User is banned from this group'], 400);
        }

        // Check member limit
        if ($group->hasReachedMemberLimit()) {
            return response()->json(['message' => 'Group has reached member limit'], 400);
        }

        $role = $request->role ?? 'member';

        // Only admins can add other admins/moderators
        if (in_array($role, ['admin', 'moderator']) && !$group->isUserAdmin($user->id)) {
            return response()->json(['message' => 'Only admins can add moderators or admins'], 403);
        }

        DB::transaction(function () use ($group, $user, $targetUserId, $role, $request) {
            $participant = $group->addParticipant($targetUserId, [
                'role' => $role,
                'permissions' => $request->permissions,
            ]);

            // Log the action
            $group->logAdminAction($user->id, 'member_added', [
                'target_user_id' => $targetUserId,
                'description' => "Added user to group with role: {$role}",
                'action_data' => [
                    'role' => $role,
                    'permissions' => $request->permissions,
                ],
            ]);

            // Record in member history
            $group->memberHistory()->create([
                'user_id' => $targetUserId,
                'action' => 'joined',
                'performed_by_user_id' => $user->id,
                'occurred_at' => now(),
            ]);
        });

        return response()->json(['message' => 'Member added successfully']);
    }

    public function removeMember(string $groupId, string $memberId): JsonResponse
    {
        $group = Conversation::groups()->findOrFail($groupId);
        $user = Auth::user();

        if (!$this->canUserRemoveMembers($group, $user->id)) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $participant = $group->activeParticipants()->findOrFail($memberId);
        $targetUserId = $participant->user_id;

        // Can't remove yourself using this endpoint
        if ($targetUserId === $user->id) {
            return response()->json(['message' => 'Use leave endpoint to remove yourself'], 400);
        }

        // Only admins can remove moderators, and can't remove other admins
        if ($participant->isAdmin() || ($participant->isModerator() && !$group->isUserAdmin($user->id))) {
            return response()->json(['message' => 'Insufficient permissions'], 403);
        }

        DB::transaction(function () use ($group, $user, $participant, $targetUserId) {
            $group->removeParticipant($targetUserId);

            $group->logAdminAction($user->id, 'member_removed', [
                'target_user_id' => $targetUserId,
                'description' => "Removed user from group",
                'action_data' => [
                    'previous_role' => $participant->role,
                ],
            ]);

            $group->memberHistory()->create([
                'user_id' => $targetUserId,
                'action' => 'removed',
                'performed_by_user_id' => $user->id,
                'occurred_at' => now(),
            ]);
        });

        return response()->json(['message' => 'Member removed successfully']);
    }

    public function updateMemberRole(Request $request, string $groupId, string $memberId): JsonResponse
    {
        $request->validate([
            'role' => 'required|in:admin,moderator,member',
            'permissions' => 'sometimes|array',
        ]);

        $group = Conversation::groups()->findOrFail($groupId);
        $user = Auth::user();

        if (!$group->isUserAdmin($user->id)) {
            return response()->json(['message' => 'Only admins can change member roles'], 403);
        }

        $participant = $group->activeParticipants()->findOrFail($memberId);
        $targetUserId = $participant->user_id;
        $newRole = $request->role;
        $oldRole = $participant->role;

        // Can't change your own role
        if ($targetUserId === $user->id) {
            return response()->json(['message' => 'Cannot change your own role'], 400);
        }

        // If removing the last admin, prevent it
        if ($participant->isAdmin() && $newRole !== 'admin' && $group->getAdminCount() === 1) {
            return response()->json(['message' => 'Cannot remove the last admin'], 400);
        }

        DB::transaction(function () use ($group, $user, $participant, $targetUserId, $newRole, $oldRole, $request) {
            $participant->update([
                'role' => $newRole,
                'permissions' => $request->permissions,
            ]);

            $actionType = $newRole === 'admin' || $oldRole === 'member' ? 'member_promoted' : 'member_demoted';

            $group->logAdminAction($user->id, $actionType, [
                'target_user_id' => $targetUserId,
                'description' => "Changed user role from {$oldRole} to {$newRole}",
                'action_data' => [
                    'new_role' => $newRole,
                    'permissions' => $request->permissions,
                ],
                'previous_values' => [
                    'role' => $oldRole,
                    'permissions' => $participant->getOriginal('permissions'),
                ],
            ]);
        });

        return response()->json(['message' => 'Member role updated successfully']);
    }

    public function banMember(Request $request, string $groupId, string $memberId): JsonResponse
    {
        $request->validate([
            'reason' => 'nullable|string|max:500',
            'duration' => 'nullable|integer|min:1', // hours
            'is_permanent' => 'boolean',
            'delete_messages' => 'boolean',
        ]);

        $group = Conversation::groups()->findOrFail($groupId);
        $user = Auth::user();

        if (!$this->canUserBanMembers($group, $user->id)) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $participant = $group->activeParticipants()->findOrFail($memberId);
        $targetUserId = $participant->user_id;

        // Can't ban yourself or other admins/moderators (depending on permissions)
        if ($targetUserId === $user->id) {
            return response()->json(['message' => 'Cannot ban yourself'], 400);
        }

        if ($participant->isAdmin() || ($participant->isModerator() && !$group->isUserAdmin($user->id))) {
            return response()->json(['message' => 'Insufficient permissions'], 403);
        }

        $expiresAt = null;
        if (!$request->boolean('is_permanent') && $request->filled('duration')) {
            $expiresAt = now()->addHours($request->duration);
        }

        DB::transaction(function () use ($group, $user, $participant, $targetUserId, $request, $expiresAt) {
            // Ban the user
            $group->banUser($targetUserId, $user->id, [
                'reason' => $request->reason,
                'is_permanent' => $request->boolean('is_permanent'),
                'expires_at' => $expiresAt,
            ]);

            // Delete messages if requested
            if ($request->boolean('delete_messages')) {
                $group->messages()
                    ->where('user_id', $targetUserId)
                    ->delete();
            }

            $group->logAdminAction($user->id, 'member_banned', [
                'target_user_id' => $targetUserId,
                'description' => "Banned user" . ($request->reason ? ": {$request->reason}" : ''),
                'action_data' => [
                    'reason' => $request->reason,
                    'is_permanent' => $request->boolean('is_permanent'),
                    'expires_at' => $expiresAt,
                    'messages_deleted' => $request->boolean('delete_messages'),
                ],
            ]);

            $group->memberHistory()->create([
                'user_id' => $targetUserId,
                'action' => 'banned',
                'performed_by_user_id' => $user->id,
                'reason' => $request->reason,
                'occurred_at' => now(),
            ]);
        });

        return response()->json(['message' => 'Member banned successfully']);
    }

    public function unbanMember(string $groupId, string $userId): JsonResponse
    {
        $group = Conversation::groups()->findOrFail($groupId);
        $user = Auth::user();

        if (!$this->canUserBanMembers($group, $user->id)) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        if (!$group->isUserBanned($userId)) {
            return response()->json(['message' => 'User is not banned'], 400);
        }

        DB::transaction(function () use ($group, $user, $userId) {
            $group->unbanUser($userId, $user->id);

            $group->logAdminAction($user->id, 'member_unbanned', [
                'target_user_id' => $userId,
                'description' => "Unbanned user",
            ]);

            $group->memberHistory()->create([
                'user_id' => $userId,
                'action' => 'unbanned',
                'performed_by_user_id' => $user->id,
                'occurred_at' => now(),
            ]);
        });

        return response()->json(['message' => 'Member unbanned successfully']);
    }

    public function muteMembers(Request $request, string $groupId): JsonResponse
    {
        $request->validate([
            'member_ids' => 'required|array',
            'member_ids.*' => 'exists:conversation_participants,id',
            'duration' => 'nullable|integer|min:1', // minutes
            'reason' => 'nullable|string|max:500',
        ]);

        $group = Conversation::groups()->findOrFail($groupId);
        $user = Auth::user();

        if (!$group->isUserModerator($user->id)) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $expiresAt = $request->filled('duration') ? now()->addMinutes($request->duration) : null;

        DB::transaction(function () use ($group, $user, $request, $expiresAt) {
            foreach ($request->member_ids as $memberId) {
                $participant = $group->activeParticipants()->find($memberId);
                if (!$participant || $participant->user_id === $user->id) {
                    continue;
                }

                // Create restriction
                $group->memberRestrictions()->create([
                    'participant_id' => $participant->id,
                    'restricted_by_user_id' => $user->id,
                    'restriction_type' => 'mute',
                    'reason' => $request->reason,
                    'expires_at' => $expiresAt,
                ]);

                $group->logAdminAction($user->id, 'member_restricted', [
                    'target_user_id' => $participant->user_id,
                    'description' => "Muted user" . ($request->reason ? ": {$request->reason}" : ''),
                    'action_data' => [
                        'restriction_type' => 'mute',
                        'reason' => $request->reason,
                        'expires_at' => $expiresAt,
                    ],
                ]);
            }
        });

        return response()->json(['message' => 'Members muted successfully']);
    }

    private function canUserViewMembers(Conversation $group, ?string $userId): bool
    {
        if ($group->isPublic() && $group->allowsAnonymousViewing()) {
            return true;
        }

        return $userId && $group->hasUser($userId);
    }

    private function canUserAddMembers(Conversation $group, string $userId): bool
    {
        if (!$group->hasUser($userId)) {
            return false;
        }

        return $group->isUserModerator($userId) || $group->canMembersAddOthers();
    }

    private function canUserRemoveMembers(Conversation $group, string $userId): bool
    {
        return $group->isUserModerator($userId);
    }

    private function canUserBanMembers(Conversation $group, string $userId): bool
    {
        return $group->isUserModerator($userId);
    }
}