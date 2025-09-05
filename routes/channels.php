<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Chat-specific channels
Broadcast::channel('conversation.{conversationId}', function ($user, $conversationId) {
    // Check if user is participant in the conversation
    return \App\Models\Chat\Conversation::where('id', $conversationId)
        ->whereHas('participants', function ($query) use ($user) {
            $query->where('user_id', $user->id)->where('left_at', null);
        })
        ->exists() ? $user->only(['id', 'name']) : false;
});

Broadcast::channel('user.{userId}.devices', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

Broadcast::channel('organization.{organizationId}.chat', function ($user, $organizationId) {
    // Check if user belongs to organization
    return $user->organizationMemberships()
        ->where('organization_id', $organizationId)
        ->where('is_active', true)
        ->exists() ? $user->only(['id', 'name']) : false;
});
