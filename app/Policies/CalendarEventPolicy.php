<?php

namespace App\Policies;

use App\Models\Calendar;
use App\Models\CalendarEvent;
use App\Models\User;

class CalendarEventPolicy
{
    /**
     * Determine whether the user can view any events.
     */
    public function viewAny(User $user): bool
    {
        // Users can view events from calendars they have access to
        return true;
    }

    /**
     * Determine whether the user can view the event.
     */
    public function view(User $user, CalendarEvent $event): bool
    {
        return $event->canView($user);
    }

    /**
     * Determine whether the user can create events.
     */
    public function create(User $user, Calendar $calendar): bool
    {
        return $calendar->canEdit($user);
    }

    /**
     * Determine whether the user can update the event.
     */
    public function update(User $user, CalendarEvent $event): bool
    {
        return $event->canEdit($user);
    }

    /**
     * Determine whether the user can delete the event.
     */
    public function delete(User $user, CalendarEvent $event): bool
    {
        return $event->canEdit($user);
    }

    /**
     * Determine whether the user can restore the event.
     */
    public function restore(User $user, CalendarEvent $event): bool
    {
        return $event->canEdit($user);
    }

    /**
     * Determine whether the user can permanently delete the event.
     */
    public function forceDelete(User $user, CalendarEvent $event): bool
    {
        return $event->calendar->canAdmin($user);
    }

    /**
     * Determine whether the user can update attendee status.
     */
    public function updateAttendeeStatus(User $user, CalendarEvent $event): bool
    {
        // Users can update their own attendee status
        if (!$event->attendees) {
            return false;
        }

        $userEmail = $user->email;
        $attendeeEmails = collect($event->attendees)->pluck('email');

        return $attendeeEmails->contains($userEmail) || $event->canEdit($user);
    }
}