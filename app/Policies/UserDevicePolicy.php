<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserDevice;

class UserDevicePolicy
{
    public function viewAny(User $user): bool
    {
        return true; // Users can view their own devices
    }

    public function view(User $user, UserDevice $device): bool
    {
        return $user->id === $device->user_id;
    }

    public function create(User $user): bool
    {
        return true; // Users can create new devices for themselves
    }

    public function update(User $user, UserDevice $device): bool
    {
        return $user->id === $device->user_id && $device->is_active;
    }

    public function delete(User $user, UserDevice $device): bool
    {
        return $user->id === $device->user_id && $device->is_active;
    }

    public function restore(User $user, UserDevice $device): bool
    {
        return $user->id === $device->user_id;
    }

    public function forceDelete(User $user, UserDevice $device): bool
    {
        return $user->id === $device->user_id;
    }
}
