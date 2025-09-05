<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ChatPermissionMiddleware
{
    public function handle(Request $request, Closure $next, string $permission, ?string $conversationParam = null): Response
    {
        $user = Auth::user();
        
        if (! $user) {
            return response()->json(['error' => 'Authentication required'], 401);
        }

        // Check global permission first
        if ($user->can($permission)) {
            return $next($request);
        }

        // Check conversation-specific permission if conversation parameter is provided
        if ($conversationParam && $request->route($conversationParam)) {
            $conversationId = $request->route($conversationParam);
            
            // Check if user has conversation-specific permission
            if ($this->hasConversationPermission($user, $conversationId, $permission)) {
                return $next($request);
            }
        }

        return response()->json([
            'error' => 'Insufficient permissions',
            'required_permission' => $permission,
        ], 403);
    }

    private function hasConversationPermission($user, string $conversationId, string $permission): bool
    {
        // Get user's role in the specific conversation
        $participant = \DB::table('chat_conversation_participants')
            ->where('conversation_id', $conversationId)
            ->where('user_id', $user->id)
            ->first();

        if (! $participant) {
            return false;
        }

        // Map conversation roles to permissions
        $conversationRolePermissions = [
            'conversation_owner' => [
                'chat.conversations.moderate',
                'chat.conversations.delete',
                'chat.messages.moderate',
                'chat.files.moderate',
                'chat.calls.moderate',
                'chat.encryption.manage',
            ],
            'conversation_admin' => [
                'chat.conversations.moderate',
                'chat.messages.moderate',
                'chat.files.moderate',
                'chat.calls.moderate',
            ],
            'conversation_member' => [
                'chat.messages.send',
                'chat.messages.edit',
                'chat.messages.delete',
                'chat.files.upload',
                'chat.files.download',
                'chat.calls.initiate',
                'chat.calls.join',
                'chat.polls.create',
                'chat.polls.vote',
            ],
            'conversation_readonly' => [
                'chat.files.download',
                'chat.calls.join',
                'chat.polls.vote',
            ],
        ];

        $conversationRole = $participant->conversation_role ?? 'conversation_member';
        $allowedPermissions = $conversationRolePermissions[$conversationRole] ?? [];

        return in_array($permission, $allowedPermissions);
    }
}