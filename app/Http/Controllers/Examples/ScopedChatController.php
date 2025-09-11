<?php

namespace App\Http\Controllers\Examples;

use App\Http\Controllers\Controller;
use App\Models\Chat\Conversation;
use App\Models\Chat\Message;
use App\Models\User;
use App\Facades\ScopedPermission;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Example controller demonstrating scoped permissions for chat
 * This shows how to implement fine-grained chat permissions
 */
class ScopedChatController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        
        // Chat-specific scoped permissions
        $this->middleware('permission.scoped:view_conversation:chat:conversation')->only(['show', 'getMessages']);
        $this->middleware('permission.scoped:send_message:chat:conversation')->only(['sendMessage']);
        $this->middleware('permission.scoped:moderate_chat:chat:conversation')->only(['moderateMessage', 'muteUser']);
        $this->middleware('permission.scoped:manage_chat:chat:conversation')->only(['updateSettings', 'addParticipant', 'removeParticipant']);
    }

    /**
     * Get conversation details
     * Requires 'view_conversation' permission in chat scope
     */
    public function show(string $conversation): JsonResponse
    {
        $conv = Conversation::with(['participants.user'])->findOrFail($conversation);
        
        return response()->json($conv);
    }

    /**
     * Get messages in conversation
     * Requires 'view_conversation' permission in chat scope
     */
    public function getMessages(Request $request, string $conversation): JsonResponse
    {
        $messages = Message::where('conversation_id', $conversation)
            ->with(['sender', 'reactions'])
            ->orderBy('created_at', 'desc')
            ->paginate(50);
            
        return response()->json($messages);
    }

    /**
     * Send message to conversation
     * Requires 'send_message' permission in chat scope
     */
    public function sendMessage(Request $request, string $conversation): JsonResponse
    {
        $request->validate([
            'content' => 'required|string',
            'type' => 'in:text,file,image',
        ]);

        $user = Auth::user();
        $conv = Conversation::findOrFail($conversation);

        $message = Message::create([
            'conversation_id' => $conversation,
            'sender_id' => $user->id,
            'content' => $request->content,
            'type' => $request->type ?? 'text',
        ]);

        return response()->json($message, 201);
    }

    /**
     * Create new conversation with scoped permissions
     */
    public function createConversation(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'nullable|string|max:255',
            'type' => 'required|in:private,group,channel',
            'participants' => 'array|min:1',
            'participants.*' => 'exists:sys_users,id',
            'organization_id' => 'nullable|exists:organizations,id',
        ]);

        $user = Auth::user();
        
        // Check organization-level permission if chat is within an organization
        if ($request->organization_id) {
            if (!$user->hasPermissionInScope('create_chat', 'organization', $request->organization_id)) {
                abort(403, 'Cannot create chat in this organization');
            }
        }

        $conversation = Conversation::create([
            'name' => $request->name,
            'type' => $request->type,
            'creator_id' => $user->id,
            'organization_id' => $request->organization_id,
        ]);

        // Set up scoped permissions for this chat
        ScopedPermission::setupScopeHierarchy(
            $conversation,
            $request->organization_id ? 'organization' : null,
            $request->organization_id,
            true,
            ['chat_type' => $request->type]
        );

        // Creator gets full permissions
        ScopedPermission::assignRoleToUser($user, 'chat_admin', 'chat', $conversation->id);

        // Add participants with member role
        $participants = User::whereIn('id', $request->participants)->get();
        foreach ($participants as $participant) {
            ScopedPermission::assignRoleToUser(
                $participant, 
                'chat_member', 
                'chat', 
                $conversation->id
            );
        }

        return response()->json($conversation, 201);
    }

    /**
     * Add participant to conversation
     * Requires 'manage_chat' permission in chat scope
     */
    public function addParticipant(Request $request, string $conversation): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:sys_users,id',
            'role' => 'in:member,moderator',
        ]);

        $user = User::findOrFail($request->user_id);
        $role = $request->role === 'moderator' ? 'chat_moderator' : 'chat_member';
        
        ScopedPermission::assignRoleToUser($user, $role, 'chat', $conversation);

        return response()->json(['message' => 'Participant added successfully']);
    }

    /**
     * Remove participant from conversation
     * Requires 'manage_chat' permission in chat scope
     */
    public function removeParticipant(string $conversation, string $userId): JsonResponse
    {
        $user = User::findOrFail($userId);
        
        // Remove all chat roles
        $chatRoles = ['chat_member', 'chat_moderator', 'chat_admin'];
        foreach ($chatRoles as $role) {
            ScopedPermission::removeRoleFromUser($user, $role, 'chat', $conversation);
        }

        return response()->json(['message' => 'Participant removed successfully']);
    }

    /**
     * Moderate message (delete/edit)
     * Requires 'moderate_chat' permission in chat scope
     */
    public function moderateMessage(Request $request, string $conversation, string $messageId): JsonResponse
    {
        $request->validate([
            'action' => 'required|in:delete,edit,flag',
            'reason' => 'required|string',
            'new_content' => 'required_if:action,edit|string',
        ]);

        $message = Message::where('conversation_id', $conversation)
            ->findOrFail($messageId);

        switch ($request->action) {
            case 'delete':
                $message->delete();
                break;
            case 'edit':
                $message->update(['content' => $request->new_content]);
                break;
            case 'flag':
                $message->update(['flagged' => true, 'flag_reason' => $request->reason]);
                break;
        }

        return response()->json(['message' => 'Message moderated successfully']);
    }

    /**
     * Mute user in conversation
     * Requires 'moderate_chat' permission in chat scope
     */
    public function muteUser(Request $request, string $conversation, string $userId): JsonResponse
    {
        $request->validate([
            'duration_minutes' => 'required|integer|min:1|max:10080', // max 1 week
            'reason' => 'required|string',
        ]);

        $user = User::findOrFail($userId);
        
        // Remove send_message permission temporarily
        ScopedPermission::revokePermissionFromUser($user, 'send_message', 'chat', $conversation);
        
        // Could also implement a separate muting system here
        // For brevity, we'll just revoke the permission

        return response()->json(['message' => 'User muted successfully']);
    }

    /**
     * Get chat permissions for current user
     */
    public function getMyPermissions(string $conversation): JsonResponse
    {
        $user = Auth::user();
        $permissions = $user->getPermissionsForScope('chat', $conversation);
        $roles = $user->getRolesForScope('chat', $conversation);
        
        return response()->json([
            'permissions' => $permissions->pluck('name'),
            'roles' => $roles->pluck('name'),
            'can_send_message' => $user->hasPermissionInScope('send_message', 'chat', $conversation),
            'can_moderate' => $user->hasPermissionInScope('moderate_chat', 'chat', $conversation),
            'can_manage' => $user->hasPermissionInScope('manage_chat', 'chat', $conversation),
        ]);
    }

    /**
     * Get all chat members and their permissions
     * Requires 'view_conversation' permission in chat scope
     */
    public function getMembers(string $conversation): JsonResponse
    {
        $members = ScopedPermission::getUsersWithPermissionInScope(
            'view_conversation',
            'chat', 
            $conversation
        );

        $memberData = $members->map(function ($member) use ($conversation) {
            return [
                'user' => $member->only(['id', 'name', 'email']),
                'permissions' => $member->getPermissionsForScope('chat', $conversation)->pluck('name'),
                'roles' => $member->getRolesForScope('chat', $conversation)->pluck('name'),
            ];
        });

        return response()->json($memberData);
    }

    /**
     * Update conversation settings
     * Requires 'manage_chat' permission in chat scope
     */
    public function updateSettings(Request $request, string $conversation): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'is_private' => 'sometimes|boolean',
            'allow_guests' => 'sometimes|boolean',
        ]);

        $conv = Conversation::findOrFail($conversation);
        $conv->update($request->only(['name', 'description', 'is_private', 'allow_guests']));

        return response()->json($conv);
    }
}