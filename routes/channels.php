<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;

// Helper function to authenticate via API token for broadcasting
function authenticateUserFromToken($token)
{
    if (! $token) {
        return null;
    }

    // Remove 'Bearer ' prefix if present
    if (str_starts_with($token, 'Bearer ')) {
        $token = substr($token, 7);
    }

    try {
        // Create a temporary request with the auth header
        $originalRequest = app('request');

        // Create new request for token validation
        $testRequest = \Illuminate\Http\Request::create('/', 'GET', [], [], [], [], '');
        $testRequest->headers->set('Authorization', 'Bearer '.$token);

        // Temporarily swap the request
        app()->instance('request', $testRequest);

        // Use the API guard to authenticate
        $guard = auth('api');
        $guard->forgetUser(); // Clear any cached user
        $user = $guard->user();

        // Restore original request
        app()->instance('request', $originalRequest);
        $guard->forgetUser(); // Clear cache again after restoring

        return $user;
    } catch (Exception $e) {
        Log::error('Broadcasting token authentication error', [
            'error' => $e->getMessage(),
            'token' => substr($token, 0, 10).'...',
        ]);

        return null;
    }
}

Broadcast::channel('user.{id}', function ($user, $id, $request = null) {
    // Try API token authentication if no session user
    if (! $user && $request) {
        $token = $request->header('Authorization') ?? $request->get('token');
        $user = authenticateUserFromToken($token);
    }

    return $user && (int) $user->id === (int) $id;
});

// Chat-specific channels (both private and public)
Broadcast::channel('conversation.{conversationId}', function ($user, $conversationId, $request = null) {
    Log::info('Conversation channel auth called', [
        'user' => $user ? $user->id : null,
        'conversation_id' => $conversationId,
        'has_request' => $request !== null,
        'auth_user' => Auth::user() ? Auth::user()->id : null,
    ]);

    // If no user passed, try to get from Auth (already authenticated by middleware)
    if (! $user) {
        $user = Auth::user();
        Log::info('Using Auth::user() for channel auth', [
            'user_id' => $user ? $user->id : null,
        ]);
    }

    // Try API token authentication if still no session user and request is available
    if (! $user && $request) {
        $token = $request->header('Authorization') ?? $request->get('token');
        Log::info('Attempting token auth in channel', [
            'has_token' => !empty($token),
            'token_prefix' => $token ? substr($token, 0, 20) . '...' : null,
        ]);

        $user = authenticateUserFromToken($token);

        Log::info('Token auth result in channel', [
            'authenticated' => $user !== null,
            'user_id' => $user ? $user->id : null,
        ]);
    }

    if (! $user) {
        Log::warning('No authenticated user for channel auth', [
            'conversation_id' => $conversationId,
        ]);
        return false;
    }

    // Check if user is participant in the conversation
    Log::info('Channel auth attempt', [
        'user_id' => $user->id,
        'conversation_id' => $conversationId,
        'user_name' => $user->name,
    ]);

    $conversation = \App\Models\Chat\Conversation::where('id', $conversationId)->first();
    if (! $conversation) {
        Log::info('Conversation not found', ['conversation_id' => $conversationId]);

        return false;
    }

    $participant = $conversation->participants()->where('user_id', $user->id)->first();
    if (! $participant) {
        Log::info('User not a participant', [
            'user_id' => $user->id,
            'conversation_id' => $conversationId,
        ]);

        return false;
    }

    if ($participant->left_at !== null) {
        Log::info('User has left the conversation', [
            'user_id' => $user->id,
            'conversation_id' => $conversationId,
            'left_at' => $participant->left_at,
        ]);

        return false;
    }

    Log::info('Channel auth successful', [
        'user_id' => $user->id,
        'conversation_id' => $conversationId,
    ]);

    return $user->only(['id', 'name']);
});

Broadcast::channel('user.{userId}.devices', function ($user, $userId, $request = null) {
    // Try API token authentication if no session user
    if (! $user && $request) {
        $token = $request->header('Authorization') ?? $request->get('token');
        $user = authenticateUserFromToken($token);
    }

    return $user && (int) $user->id === (int) $userId;
});

Broadcast::channel('organization.{organizationId}.chat', function ($user, $organizationId, $request = null) {
    // Try API token authentication if no session user
    if (! $user && $request) {
        $token = $request->header('Authorization') ?? $request->get('token');
        $user = authenticateUserFromToken($token);
    }

    if (! $user) {
        return false;
    }

    // Check if user belongs to organization
    return $user->organizationMemberships()
        ->where('organization_id', $organizationId)
        ->where('is_active', true)
        ->exists() ? $user->only(['id', 'name']) : false;
});
