<?php

namespace App\Policies;

use App\Models\Calendar;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;

class CalendarPolicy
{
    /**
     * Determine whether the user can view any calendars.
     */
    public function viewAny(User $user): bool
    {
        // Users can view calendars they have access to
        return true;
    }

    /**
     * Determine whether the user can view the calendar.
     */
    public function view(User $user, Calendar $calendar): bool
    {
        return $calendar->canView($user);
    }

    /**
     * Determine whether the user can create a calendar for the given owner.
     */
    public function createCalendarFor(User $user, $owner): bool
    {
        if ($owner instanceof User) {
            // Users can only create calendars for themselves
            return $owner->id === $user->id;
        }

        if ($owner instanceof Organization) {
            // Check if user is a member of the organization with appropriate permissions
            return $owner->activeUsers()->where('user_id', $user->id)->exists();
        }

        if ($owner instanceof Project) {
            // Check if user is a member of the project
            return $owner->users()->where('user_id', $user->id)->exists();
        }

        return false;
    }

    /**
     * Determine whether the user can create calendars.
     */
    public function create(User $user): bool
    {
        // Basic permission to create calendars
        return true;
    }

    /**
     * Determine whether the user can update the calendar.
     */
    public function update(User $user, Calendar $calendar): bool
    {
        return $calendar->canEdit($user);
    }

    /**
     * Determine whether the user can delete the calendar.
     */
    public function delete(User $user, Calendar $calendar): bool
    {
        return $calendar->canAdmin($user);
    }

    /**
     * Determine whether the user can restore the calendar.
     */
    public function restore(User $user, Calendar $calendar): bool
    {
        return $calendar->canAdmin($user);
    }

    /**
     * Determine whether the user can permanently delete the calendar.
     */
    public function forceDelete(User $user, Calendar $calendar): bool
    {
        return $calendar->canAdmin($user);
    }

    /**
     * Determine whether the user can manage calendar permissions (share/revoke).
     */
    public function admin(User $user, Calendar $calendar): bool
    {
        return $calendar->canAdmin($user);
    }
}