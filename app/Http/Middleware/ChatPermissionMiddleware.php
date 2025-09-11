<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ChatPermissionMiddleware
{
    public function handle(Request $request, Closure $next, string $permission, ?string $conversationParam = null): Response
    {
        $user = Auth::user();

        if (! $user) {
            Log::info('ChatPermissionMiddleware: No authenticated user', [
                'path' => $request->path(),
                'permission' => $permission,
                'conversation_param' => $conversationParam,
            ]);
            return response()->json(['error' => 'Authentication required'], 401);
        }

        Log::info('ChatPermissionMiddleware: Checking permission', [
            'user_id' => $user->id,
            'path' => $request->path(),
            'permission' => $permission,
            'conversation_param' => $conversationParam,
            'route_conversation' => $request->route($conversationParam),
        ]);

        // Define basic chat permissions that all authenticated users should have
        $basicChatPermissions = [
            'chat:read',
            'chat:write',
            'chat:files',
            'chat:calls',
        ];

        // If this is a basic chat permission, automatically grant it to authenticated users
        if (in_array($permission, $basicChatPermissions)) {
            Log::info('ChatPermissionMiddleware: Granting basic permission', [
                'user_id' => $user->id,
                'permission' => $permission,
            ]);
            return $next($request);
        }

        // Check global permission for advanced permissions (manage, moderate, admin)
        if ($user->can($permission)) {
            Log::info('ChatPermissionMiddleware: Granted global permission', [
                'user_id' => $user->id,
                'permission' => $permission,
            ]);
            return $next($request);
        }

        // Check conversation-specific permission if conversation parameter is provided
        if ($conversationParam && $request->route($conversationParam)) {
            $conversationId = $request->route($conversationParam);

            // Check if user has conversation-specific permission
            if ($this->hasConversationPermission($user, $conversationId, $permission)) {
                Log::info('ChatPermissionMiddleware: Granted conversation permission', [
                    'user_id' => $user->id,
                    'permission' => $permission,
                    'conversation_id' => $conversationId,
                ]);
                return $next($request);
            }
        }

        Log::info('ChatPermissionMiddleware: Permission denied', [
            'user_id' => $user->id,
            'permission' => $permission,
            'conversation_param' => $conversationParam,
            'route_conversation' => $request->route($conversationParam),
        ]);

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
                'chat:moderate',
                'chat:admin',
                'chat:manage',
                'chat:write',
                'chat:read',
                'chat:files',
                'chat:calls',
            ],
            'conversation_admin' => [
                'chat:moderate',
                'chat:manage',
                'chat:write',
                'chat:read',
                'chat:files',
                'chat:calls',
            ],
            'conversation_member' => [
                'chat:write',
                'chat:read',
                'chat:files',
                'chat:calls',
            ],
            'conversation_readonly' => [
                'chat:read',
                'chat:files',
            ],
        ];

        $conversationRole = $participant->conversation_role ?? 'conversation_member';
        $allowedPermissions = $conversationRolePermissions[$conversationRole] ?? [];

        return in_array($permission, $allowedPermissions);
    }
}
