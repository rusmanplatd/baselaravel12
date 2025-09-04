<?php

namespace App\Policies\Chat;

use App\Models\Chat\Conversation;
use App\Models\User;

class ConversationPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Conversation $conversation): bool
    {
        return $conversation->participants()
            ->where('user_id', $user->id)
            ->whereNull('left_at')
            ->exists();
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Conversation $conversation): bool
    {
        $participant = $conversation->participants()
            ->where('user_id', $user->id)
            ->first();

        if (!$participant || !$participant->isActive()) {
            return false;
        }

        // For direct conversations, any active participant can update (especially for encryption setup)
        if ($conversation->type === 'direct') {
            return true;
        }

        // For group conversations, only admins can update
        return $participant->isAdmin();
    }

    public function delete(User $user, Conversation $conversation): bool
    {
        $participant = $conversation->participants()
            ->where('user_id', $user->id)
            ->first();

        return $participant && $participant->isAdmin() && $participant->isActive();
    }

    public function participate(User $user, Conversation $conversation): bool
    {
        return $conversation->participants()
            ->where('user_id', $user->id)
            ->whereNull('left_at')
            ->exists();
    }

    public function restore(User $user, Conversation $conversation): bool
    {
        return $this->delete($user, $conversation);
    }

    public function forceDelete(User $user, Conversation $conversation): bool
    {
        return $this->delete($user, $conversation);
    }
}
