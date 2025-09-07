<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chat\Conversation;
use App\Models\GroupInvitation;
use App\Models\GroupInviteLink;
use App\Models\GroupJoinRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GroupController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Conversation::groups()
            ->active()
            ->with(['creator', 'activeParticipants'])
            ->withMemberCount();

        // Filter by privacy
        if ($request->filled('privacy')) {
            $query->byPrivacy($request->privacy);
        }

        // Search by name or username
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', "%{$search}%")
                    ->orWhere('username', 'ILIKE', "%{$search}%");
            });
        }

        // Filter by organization
        if ($request->filled('organization_id')) {
            $query->where('organization_id', $request->organization_id);
        }

        $groups = $query->latest('last_activity_at')
            ->paginate($request->per_page ?? 20);

        return response()->json($groups);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:group,channel',
            'privacy' => 'required|in:private,public,invite_only',
            'username' => 'nullable|string|unique:chat_conversations,username|regex:/^[a-zA-Z0-9_]+$/',
            'description' => 'nullable|string|max:1000',
            'welcome_message' => 'nullable|string|max:2000',
            'member_limit' => 'nullable|integer|min:2|max:200000',
            'can_members_add_others' => 'boolean',
            'require_approval_to_join' => 'boolean',
            'show_member_count' => 'boolean',
            'allow_anonymous_viewing' => 'boolean',
            'avatar_url' => 'nullable|url',
            'organization_id' => 'nullable|exists:organizations,id',
        ]);

        $user = Auth::user();

        $group = DB::transaction(function () use ($request, $user) {
            $group = Conversation::create([
                'name' => $request->name,
                'type' => $request->type,
                'privacy' => $request->privacy,
                'username' => $request->username,
                'description' => $request->description,
                'welcome_message' => $request->welcome_message,
                'member_limit' => $request->member_limit,
                'can_members_add_others' => $request->boolean('can_members_add_others'),
                'require_approval_to_join' => $request->boolean('require_approval_to_join'),
                'show_member_count' => $request->boolean('show_member_count'),
                'allow_anonymous_viewing' => $request->boolean('allow_anonymous_viewing'),
                'avatar_url' => $request->avatar_url,
                'created_by_user_id' => $user->id,
                'organization_id' => $request->organization_id,
            ]);

            // Add creator as admin
            $group->addParticipant($user->id, [
                'role' => 'admin',
            ]);

            // Log creation
            $group->logAdminAction($user->id, 'group_settings_changed', [
                'description' => "Created group '{$group->name}'",
                'action_data' => [
                    'group_created' => true,
                    'initial_settings' => $group->only(['name', 'type', 'privacy', 'username']),
                ],
            ]);

            return $group;
        });

        return response()->json([
            'message' => 'Group created successfully',
            'group' => $group->load(['creator', 'activeParticipants']),
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $group = Conversation::groups()
            ->with([
                'creator',
                'activeParticipants.user',
                'pinnedMessages.message',
                'topics' => fn($q) => $q->where('is_active', true)->orderBy('sort_order'),
            ])
            ->withMemberCount()
            ->findOrFail($id);

        $user = Auth::user();

        // Check if user can view this group
        if (!$this->canUserViewGroup($group, $user?->id)) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $response = [
            'group' => $group,
            'user_role' => $group->getUserRole($user?->id),
            'can_send_messages' => $user ? $group->participants()
                ->where('user_id', $user->id)
                ->active()
                ->first()?->canSendMessages() ?? false : false,
        ];

        return response()->json($response);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $group = Conversation::groups()->findOrFail($id);
        $user = Auth::user();

        // Check if user can manage this group
        if (!$group->isUserModerator($user->id)) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'privacy' => 'sometimes|required|in:private,public,invite_only',
            'username' => 'sometimes|nullable|string|unique:chat_conversations,username,' . $group->id . '|regex:/^[a-zA-Z0-9_]+$/',
            'description' => 'nullable|string|max:1000',
            'welcome_message' => 'nullable|string|max:2000',
            'member_limit' => 'nullable|integer|min:2|max:200000',
            'can_members_add_others' => 'boolean',
            'require_approval_to_join' => 'boolean',
            'show_member_count' => 'boolean',
            'allow_anonymous_viewing' => 'boolean',
            'avatar_url' => 'nullable|url',
        ]);

        $originalData = $group->only([
            'name', 'privacy', 'username', 'description', 'welcome_message',
            'member_limit', 'can_members_add_others', 'require_approval_to_join',
            'show_member_count', 'allow_anonymous_viewing', 'avatar_url'
        ]);

        $group->update($request->only([
            'name', 'privacy', 'username', 'description', 'welcome_message',
            'member_limit', 'can_members_add_others', 'require_approval_to_join',
            'show_member_count', 'allow_anonymous_viewing', 'avatar_url'
        ]));

        // Log the changes
        $changes = array_diff_assoc($request->only([
            'name', 'privacy', 'username', 'description', 'welcome_message',
            'member_limit', 'can_members_add_others', 'require_approval_to_join',
            'show_member_count', 'allow_anonymous_viewing', 'avatar_url'
        ]), $originalData);

        if (!empty($changes)) {
            $group->logAdminAction($user->id, 'group_settings_changed', [
                'description' => "Updated group settings",
                'action_data' => ['changes' => $changes],
                'previous_values' => $originalData,
            ]);
        }

        return response()->json([
            'message' => 'Group updated successfully',
            'group' => $group->fresh(['creator', 'activeParticipants']),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $group = Conversation::groups()->findOrFail($id);
        $user = Auth::user();

        // Only group creator or admin can delete
        if (!$group->isUserAdmin($user->id) && $group->created_by_user_id !== $user->id) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        // Log deletion before actual deletion
        $group->logAdminAction($user->id, 'group_settings_changed', [
            'description' => "Deleted group '{$group->name}'",
            'action_data' => ['group_deleted' => true],
        ]);

        $group->delete();

        return response()->json(['message' => 'Group deleted successfully']);
    }

    public function join(Request $request, string $id): JsonResponse
    {
        $group = Conversation::groups()->findOrFail($id);
        $user = Auth::user();

        if (!$group->canUserJoin($user->id)) {
            return response()->json(['message' => 'Cannot join this group'], 403);
        }

        DB::transaction(function () use ($group, $user, $request) {
            if ($group->requiresApprovalToJoin() && !$group->isPublic()) {
                // Create join request
                $group->joinRequests()->create([
                    'user_id' => $user->id,
                    'request_message' => $request->message,
                ]);

                $group->logAdminAction($user->id, 'user_joined', [
                    'description' => "Requested to join group",
                    'action_data' => ['join_request_created' => true],
                ]);
            } else {
                // Add user directly
                $group->addParticipant($user->id);

                $group->logAdminAction($user->id, 'user_joined', [
                    'description' => "Joined group",
                    'action_data' => ['joined_directly' => true],
                ]);
            }
        });

        return response()->json([
            'message' => $group->requiresApprovalToJoin() && !$group->isPublic()
                ? 'Join request submitted'
                : 'Joined group successfully'
        ]);
    }

    public function leave(string $id): JsonResponse
    {
        $group = Conversation::groups()->findOrFail($id);
        $user = Auth::user();

        if (!$group->hasUser($user->id)) {
            return response()->json(['message' => 'You are not a member of this group'], 400);
        }

        // Check if user is the only admin
        if ($group->isUserAdmin($user->id) && $group->getAdminCount() === 1 && $group->getParticipantCount() > 1) {
            return response()->json([
                'message' => 'Cannot leave group as the only admin. Promote another member to admin first.'
            ], 400);
        }

        DB::transaction(function () use ($group, $user) {
            $group->removeParticipant($user->id);

            $group->logAdminAction($user->id, 'user_left', [
                'description' => "Left the group",
                'action_data' => ['voluntary_leave' => true],
            ]);

            // Record in member history
            $group->memberHistory()->create([
                'user_id' => $user->id,
                'action' => 'left',
                'performed_by_user_id' => $user->id,
                'occurred_at' => now(),
            ]);
        });

        return response()->json(['message' => 'Left group successfully']);
    }

    private function canUserViewGroup(Conversation $group, ?string $userId): bool
    {
        // Public groups can be viewed by anyone
        if ($group->isPublic()) {
            return true;
        }

        // For private/invite-only groups, user must be a member
        if ($userId) {
            return $group->hasUser($userId);
        }

        return false;
    }
}