<?php

namespace App\Policies\Chat;

use App\Models\Chat\Channel;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ChannelPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Channel $channel): bool
    {
        if ($channel->isPublic()) {
            return true;
        }

        return $channel->activeParticipants()
            ->where('user_id', $user->id)
            ->exists();
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Channel $channel): bool
    {
        $participant = $channel->activeParticipants()
            ->where('user_id', $user->id)
            ->first();

        return $participant && in_array($participant->role, ['owner', 'admin']);
    }

    public function delete(User $user, Channel $channel): bool
    {
        $participant = $channel->activeParticipants()
            ->where('user_id', $user->id)
            ->first();

        return $participant && $participant->role === 'owner';
    }

    public function join(User $user, Channel $channel): bool
    {
        if ($channel->isPrivate()) {
            return false;
        }

        $existingParticipant = $channel->activeParticipants()
            ->where('user_id', $user->id)
            ->exists();

        return !$existingParticipant;
    }

    public function leave(User $user, Channel $channel): bool
    {
        return $channel->activeParticipants()
            ->where('user_id', $user->id)
            ->exists();
    }

    public function invite(User $user, Channel $channel): bool
    {
        if ($channel->isPublic()) {
            return true;
        }

        $participant = $channel->activeParticipants()
            ->where('user_id', $user->id)
            ->first();

        return $participant && in_array($participant->role, ['owner', 'admin']);
    }

    public function manageMembers(User $user, Channel $channel): bool
    {
        $participant = $channel->activeParticipants()
            ->where('user_id', $user->id)
            ->first();

        return $participant && in_array($participant->role, ['owner', 'admin']);
    }
}