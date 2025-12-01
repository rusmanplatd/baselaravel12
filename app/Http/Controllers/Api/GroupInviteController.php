<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chat\Conversation;
use App\Models\GroupInvitation;
use App\Models\GroupInviteLink;
use App\Models\GroupJoinRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class GroupInviteController extends Controller
{
    // Direct Invitations
    public function createInvitation(Request $request, string $groupId): JsonResponse
    {
        $request->validate([
            'user_id' => 'required_without:email|exists:sys_users,id',
            'email' => 'required_without:user_id|email',
            'phone_number' => 'nullable|string',
            'invitation_type' => 'required|in:direct,email,phone',
            'role' => 'sometimes|in:admin,moderator,member',
            'permissions' => 'sometimes|array',
            'invitation_message' => 'nullable|string|max:500',
            'expires_at' => 'nullable|date|after:now',
        ]);

        $group = Conversation::groups()->findOrFail($groupId);
        $user = Auth::user();

        // Check permissions
        if (!$this->canUserInviteMembers($group, $user->id)) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        // Check if member limit would be exceeded
        if ($group->hasReachedMemberLimit()) {
            return response()->json(['message' => 'Group has reached member limit'], 400);
        }

        $role = $request->role ?? 'member';

        // Only admins can invite with admin/moderator roles
        if (in_array($role, ['admin', 'moderator']) && !$group->isUserAdmin($user->id)) {
            return response()->json(['message' => 'Only admins can invite moderators or admins'], 403);
        }

        // If inviting by user_id, check if user is already a member or banned
        if ($request->filled('user_id')) {
            if ($group->hasUser($request->user_id)) {
                return response()->json(['message' => 'User is already a member'], 400);
            }
            if ($group->isUserBanned($request->user_id)) {
                return response()->json(['message' => 'User is banned from this group'], 400);
            }
        }

        $invitation = DB::transaction(function () use ($group, $user, $request, $role) {
            $invitation = $group->invitations()->create([
                'invited_by_user_id' => $user->id,
                'invited_user_id' => $request->user_id,
                'email' => $request->email,
                'phone_number' => $request->phone_number,
                'invitation_type' => $request->invitation_type,
                'role' => $role,
                'permissions' => $request->permissions,
                'invitation_message' => $request->invitation_message,
                'expires_at' => $request->expires_at,
            ]);

            $group->logAdminAction($user->id, 'invite_link_created', [
                'description' => "Created invitation for " . ($request->email ?? 'user'),
                'action_data' => [
                    'invitation_type' => $request->invitation_type,
                    'role' => $role,
                    'expires_at' => $request->expires_at,
                ],
            ]);

            return $invitation;
        });

        // Send invitation email/SMS if needed
        if ($request->invitation_type === 'email' && $request->filled('email')) {
            // TODO: Send invitation email
        }

        return response()->json([
            'message' => 'Invitation created successfully',
            'invitation' => $invitation,
            'invite_url' => $invitation->getInviteUrl(),
        ], 201);
    }

    public function acceptInvitation(string $token): JsonResponse
    {
        $invitation = GroupInvitation::where('invitation_token', $token)
            ->with(['conversation', 'invitedBy'])
            ->firstOrFail();

        $user = Auth::user();

        if (!$invitation->canBeAccepted()) {
            $status = $invitation->isExpired() ? 'expired' : $invitation->status;
            return response()->json(['message' => "Invitation is {$status}"], 400);
        }

        // If invitation is for a specific user, verify it matches
        if ($invitation->invited_user_id && $invitation->invited_user_id !== $user->id) {
            return response()->json(['message' => 'This invitation is for a different user'], 403);
        }

        $group = $invitation->conversation;

        // Check if user can still join
        if (!$group->canUserJoin($user->id)) {
            return response()->json(['message' => 'Cannot join this group'], 403);
        }

        DB::transaction(function () use ($invitation, $group, $user) {
            // Accept the invitation
            $invitation->accept();

            // Add user to group
            $group->addParticipant($user->id, [
                'role' => $invitation->role,
                'permissions' => $invitation->permissions,
            ]);

            // Log the action
            $group->logAdminAction($user->id, 'user_joined', [
                'description' => "Joined group via invitation",
                'action_data' => [
                    'via_invitation' => true,
                    'invited_by' => $invitation->invited_by_user_id,
                ],
            ]);

            // Record in member history
            $group->memberHistory()->create([
                'user_id' => $user->id,
                'action' => 'joined',
                'performed_by_user_id' => $invitation->invited_by_user_id,
                'occurred_at' => now(),
            ]);
        });

        return response()->json([
            'message' => 'Successfully joined the group',
            'group' => $group->load(['creator', 'activeParticipants']),
        ]);
    }

    public function rejectInvitation(string $token): JsonResponse
    {
        $invitation = GroupInvitation::where('invitation_token', $token)->firstOrFail();
        $user = Auth::user();

        if (!$invitation->canBeRejected()) {
            return response()->json(['message' => 'Invitation cannot be rejected'], 400);
        }

        // If invitation is for a specific user, verify it matches
        if ($invitation->invited_user_id && $invitation->invited_user_id !== $user->id) {
            return response()->json(['message' => 'This invitation is for a different user'], 403);
        }

        $invitation->reject();

        return response()->json(['message' => 'Invitation rejected']);
    }

    // Invite Links
    public function getInviteLinks(string $groupId): JsonResponse
    {
        $group = Conversation::groups()->findOrFail($groupId);
        $user = Auth::user();

        if (!$group->isUserModerator($user->id)) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $links = $group->inviteLinks()
            ->with(['createdBy:id,name,email', 'usages.user:id,name'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($links);
    }

    public function createInviteLink(Request $request, string $groupId): JsonResponse
    {
        $request->validate([
            'name' => 'nullable|string|max:255',
            'role' => 'sometimes|in:admin,moderator,member',
            'permissions' => 'sometimes|array',
            'usage_limit' => 'nullable|integer|min:1|max:100000',
            'expires_at' => 'nullable|date|after:now',
        ]);

        $group = Conversation::groups()->findOrFail($groupId);
        $user = Auth::user();

        if (!$group->isUserModerator($user->id)) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $role = $request->role ?? 'member';

        // Only admins can create links with admin/moderator roles
        if (in_array($role, ['admin', 'moderator']) && !$group->isUserAdmin($user->id)) {
            return response()->json(['message' => 'Only admins can create invite links for moderators or admins'], 403);
        }

        $inviteLink = DB::transaction(function () use ($group, $user, $request, $role) {
            $inviteLink = $group->createInviteLink($user->id, [
                'name' => $request->name,
                'role' => $role,
                'permissions' => $request->permissions,
                'usage_limit' => $request->usage_limit,
                'expires_at' => $request->expires_at,
            ]);

            $group->logAdminAction($user->id, 'invite_link_created', [
                'description' => "Created invite link" . ($request->name ? ": {$request->name}" : ''),
                'action_data' => [
                    'role' => $role,
                    'usage_limit' => $request->usage_limit,
                    'expires_at' => $request->expires_at,
                ],
            ]);

            return $inviteLink;
        });

        return response()->json([
            'message' => 'Invite link created successfully',
            'invite_link' => $inviteLink,
            'invite_url' => $inviteLink->getInviteUrl(),
        ], 201);
    }

    public function useInviteLink(string $token): JsonResponse
    {
        $inviteLink = GroupInviteLink::where('link_token', $token)
            ->with(['conversation', 'createdBy'])
            ->firstOrFail();

        $user = Auth::user();
        $group = $inviteLink->conversation;

        if (!$inviteLink->canBeUsed()) {
            $reason = $inviteLink->isExpired() ? 'expired' : ($inviteLink->isExhausted() ? 'exhausted' : 'inactive');
            return response()->json(['message' => "Invite link is {$reason}"], 400);
        }

        // Check if user can join
        if (!$group->canUserJoin($user->id)) {
            return response()->json(['message' => 'Cannot join this group'], 403);
        }

        DB::transaction(function () use ($inviteLink, $group, $user) {
            // Use the invite link
            $inviteLink->use($user, [
                'user_agent' => request()->userAgent(),
                'ip_address' => request()->ip(),
            ]);

            // Add user to group
            $group->addParticipant($user->id, [
                'role' => $inviteLink->role,
                'permissions' => $inviteLink->permissions,
            ]);

            // Log the action
            $group->logAdminAction($user->id, 'invite_link_used', [
                'description' => "Joined group via invite link",
                'action_data' => [
                    'invite_link_id' => $inviteLink->id,
                    'link_name' => $inviteLink->name,
                ],
            ]);

            // Record in member history
            $group->memberHistory()->create([
                'user_id' => $user->id,
                'action' => 'joined',
                'performed_by_user_id' => $inviteLink->created_by_user_id,
                'occurred_at' => now(),
            ]);
        });

        return response()->json([
            'message' => 'Successfully joined the group',
            'group' => $group->load(['creator', 'activeParticipants']),
        ]);
    }

    public function revokeInviteLink(string $groupId, string $linkId): JsonResponse
    {
        $group = Conversation::groups()->findOrFail($groupId);
        $user = Auth::user();

        if (!$group->isUserModerator($user->id)) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $inviteLink = $group->inviteLinks()->findOrFail($linkId);

        DB::transaction(function () use ($group, $user, $inviteLink) {
            $inviteLink->revoke();

            $group->logAdminAction($user->id, 'invite_link_revoked', [
                'description' => "Revoked invite link" . ($inviteLink->name ? ": {$inviteLink->name}" : ''),
                'action_data' => [
                    'link_id' => $inviteLink->id,
                    'usage_count' => $inviteLink->usage_count,
                ],
            ]);
        });

        return response()->json(['message' => 'Invite link revoked successfully']);
    }

    // Join Requests
    public function getJoinRequests(string $groupId): JsonResponse
    {
        $group = Conversation::groups()->findOrFail($groupId);
        $user = Auth::user();

        if (!$group->isUserModerator($user->id)) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $requests = $group->joinRequests()
            ->with(['user:id,name,email,avatar_url', 'reviewedBy:id,name'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($requests);
    }

    public function approveJoinRequest(Request $request, string $groupId, string $requestId): JsonResponse
    {
        $request->validate([
            'role' => 'sometimes|in:admin,moderator,member',
            'permissions' => 'sometimes|array',
        ]);

        $group = Conversation::groups()->findOrFail($groupId);
        $user = Auth::user();

        if (!$group->isUserModerator($user->id)) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $joinRequest = $group->joinRequests()
            ->where('status', 'pending')
            ->findOrFail($requestId);

        $role = $request->role ?? 'member';

        // Only admins can approve with admin/moderator roles
        if (in_array($role, ['admin', 'moderator']) && !$group->isUserAdmin($user->id)) {
            return response()->json(['message' => 'Only admins can approve with moderator or admin roles'], 403);
        }

        // Check if user can still join
        if (!$group->canUserJoin($joinRequest->user_id)) {
            return response()->json(['message' => 'User cannot join this group'], 403);
        }

        DB::transaction(function () use ($group, $user, $joinRequest, $role, $request) {
            // Approve the request
            $joinRequest->update([
                'status' => 'approved',
                'reviewed_by_user_id' => $user->id,
                'reviewed_at' => now(),
            ]);

            // Add user to group
            $group->addParticipant($joinRequest->user_id, [
                'role' => $role,
                'permissions' => $request->permissions,
            ]);

            // Log the action
            $group->logAdminAction($user->id, 'member_added', [
                'target_user_id' => $joinRequest->user_id,
                'description' => "Approved join request",
                'action_data' => [
                    'via_join_request' => true,
                    'role' => $role,
                ],
            ]);

            // Record in member history
            $group->memberHistory()->create([
                'user_id' => $joinRequest->user_id,
                'action' => 'joined',
                'performed_by_user_id' => $user->id,
                'occurred_at' => now(),
            ]);
        });

        return response()->json(['message' => 'Join request approved']);
    }

    public function rejectJoinRequest(Request $request, string $groupId, string $requestId): JsonResponse
    {
        $request->validate([
            'review_message' => 'nullable|string|max:500',
        ]);

        $group = Conversation::groups()->findOrFail($groupId);
        $user = Auth::user();

        if (!$group->isUserModerator($user->id)) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $joinRequest = $group->joinRequests()
            ->where('status', 'pending')
            ->findOrFail($requestId);

        $joinRequest->update([
            'status' => 'rejected',
            'reviewed_by_user_id' => $user->id,
            'review_message' => $request->review_message,
            'reviewed_at' => now(),
        ]);

        $group->logAdminAction($user->id, 'member_removed', [
            'target_user_id' => $joinRequest->user_id,
            'description' => "Rejected join request",
            'action_data' => [
                'reason' => $request->review_message,
            ],
        ]);

        return response()->json(['message' => 'Join request rejected']);
    }

    private function canUserInviteMembers(Conversation $group, string $userId): bool
    {
        if (!$group->hasUser($userId)) {
            return false;
        }

        return $group->isUserModerator($userId) || $group->canMembersAddOthers();
    }
}