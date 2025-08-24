<?php

use App\Models\Chat\Conversation;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('chat.conversation.{conversationId}', function ($user, $conversationId) {
    $conversation = Conversation::find($conversationId);

    if (! $conversation) {
        return false;
    }

    return $conversation->participants()
        ->where('user_id', $user->id)
        ->whereNull('left_at')
        ->exists();
});

Broadcast::channel('chat.user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});
