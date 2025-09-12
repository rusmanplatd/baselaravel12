<?php

namespace App\Http\Controllers;

use App\Models\MeetingCalendarIntegration;
use App\Services\MeetingService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MeetingController extends Controller
{
    public function __construct(
        protected MeetingService $meetingService
    ) {}

    /**
     * Show the meeting join page
     */
    public function join(Request $request, MeetingCalendarIntegration $meeting)
    {
        $user = $request->user();
        
        // Check if user is an attendee
        $attendee = $meeting->attendees()
            ->where('user_id', $user->id)
            ->first();

        if (!$attendee && !$meeting->canAutoJoin()) {
            abort(403, 'You are not invited to this meeting');
        }

        // Get meeting info
        $meetingInfo = $meeting->getMeetingInfo();
        
        // Check if meeting is ready to join
        $canJoin = in_array($meeting->status, ['scheduled', 'active']);
        $meetingStarted = $meeting->status === 'active';

        return Inertia::render('meeting/join', [
            'meeting' => $meetingInfo,
            'attendee' => $attendee?->getAttendeeInfo(),
            'canJoin' => $canJoin,
            'meetingStarted' => $meetingStarted,
            'calendarEvent' => [
                'title' => $meeting->calendarEvent->title,
                'description' => $meeting->calendarEvent->description,
                'starts_at' => $meeting->calendarEvent->starts_at,
                'ends_at' => $meeting->calendarEvent->ends_at,
                'location' => $meeting->calendarEvent->location,
            ],
        ]);
    }

    /**
     * Show the meeting host page
     */
    public function host(Request $request, MeetingCalendarIntegration $meeting)
    {
        $user = $request->user();
        
        // Check if user is a host or co-host
        $attendee = $meeting->attendees()
            ->where('user_id', $user->id)
            ->whereIn('role', ['host', 'co-host'])
            ->first();

        if (!$attendee) {
            abort(403, 'You do not have host permissions for this meeting');
        }

        // Get meeting info with additional host data
        $meetingInfo = $meeting->getMeetingInfo();
        $participants = $this->meetingService->getMeetingParticipants($meeting);

        return Inertia::render('meeting/host', [
            'meeting' => $meetingInfo,
            'attendee' => $attendee->getAttendeeInfo(),
            'participants' => $participants,
            'calendarEvent' => [
                'title' => $meeting->calendarEvent->title,
                'description' => $meeting->calendarEvent->description,
                'starts_at' => $meeting->calendarEvent->starts_at,
                'ends_at' => $meeting->calendarEvent->ends_at,
                'location' => $meeting->calendarEvent->location,
                'attendees' => $meeting->calendarEvent->attendees,
            ],
            'hostControls' => [
                'canStart' => $meeting->isScheduled(),
                'canEnd' => $meeting->isActive(),
                'canUpdateSettings' => true,
                'canManageAttendees' => true,
            ],
        ]);
    }
}