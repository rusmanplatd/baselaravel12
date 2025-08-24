<?php

namespace App\Policies\Chat;

use App\Models\Chat\Message;
use App\Models\User;

class MessagePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Message $message): bool
    {
        return $message->conversation->participants()
            ->where('user_id', $user->id)
            ->whereNull('left_at')
            ->exists();
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Message $message): bool
    {
        return $message->sender_id === $user->id &&
               $message->created_at->diffInMinutes(now()) <= 15;
    }

    public function delete(User $user, Message $message): bool
    {
        if ($message->sender_id === $user->id) {
            return true;
        }

        $participant = $message->conversation->participants()
            ->where('user_id', $user->id)
            ->first();

        return $participant && $participant->isAdmin();
    }

    public function restore(User $user, Message $message): bool
    {
        return $this->delete($user, $message);
    }

    public function forceDelete(User $user, Message $message): bool
    {
        return $this->delete($user, $message);
    }
}
