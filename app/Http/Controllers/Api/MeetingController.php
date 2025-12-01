<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CalendarEvent;
use App\Models\MeetingCalendarIntegration;
use App\Services\MeetingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MeetingController extends Controller
{
    public function __construct(
        protected MeetingService $meetingService
    ) {}

    /**
     * Create a meeting from a calendar event
     */
    public function createFromCalendarEvent(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'calendar_event_id' => 'required|exists:calendar_events,id',
            'meeting_settings' => 'array',
            'meeting_settings.audio_enabled' => 'boolean',
            'meeting_settings.video_enabled' => 'boolean',
            'meeting_settings.screen_sharing_enabled' => 'boolean',
            'meeting_settings.chat_enabled' => 'boolean',
            'meeting_settings.waiting_room_enabled' => 'boolean',
            'meeting_settings.max_participants' => 'integer|min:2|max:100',
            'meeting_settings.recording_enabled' => 'boolean',
            'meeting_settings.e2ee_enabled' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $calendarEvent = CalendarEvent::findOrFail($request->calendar_event_id);
            
            // Check if meeting already exists
            if ($calendarEvent->meetingIntegration) {
                return response()->json([
                    'success' => false,
                    'message' => 'Meeting already exists for this calendar event',
                    'meeting' => $calendarEvent->meetingIntegration->getMeetingInfo(),
                ], 409);
            }

            $meetingSettings = $request->input('meeting_settings', []);
            $meeting = $this->meetingService->createMeetingFromCalendarEvent($calendarEvent, $meetingSettings);

            return response()->json([
                'success' => true,
                'message' => 'Meeting created successfully',
                'meeting' => $meeting->getMeetingInfo(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create meeting: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get meeting information
     */
    public function show(MeetingCalendarIntegration $meeting): JsonResponse
    {
        return response()->json([
            'success' => true,
            'meeting' => $meeting->load(['calendarEvent', 'attendees.user'])->getMeetingInfo(),
        ]);
    }

    /**
     * Start a meeting
     */
    public function start(MeetingCalendarIntegration $meeting): JsonResponse
    {
        try {
            $result = $this->meetingService->startMeeting($meeting);

            return response()->json([
                'success' => true,
                'message' => 'Meeting started successfully',
                'meeting' => $result['meeting']->getMeetingInfo(),
                'join_url' => $result['join_url'],
                'host_url' => $result['host_url'],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to start meeting: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Join a meeting
     */
    public function join(MeetingCalendarIntegration $meeting, Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $permissions = $request->input('permissions', []);
            
            $connectionDetails = $this->meetingService->joinMeeting($meeting, $user, $permissions);

            return response()->json([
                'success' => true,
                'message' => 'Meeting join details generated',
                'connection' => $connectionDetails,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to join meeting: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * End a meeting
     */
    public function end(MeetingCalendarIntegration $meeting): JsonResponse
    {
        try {
            $this->meetingService->endMeeting($meeting);

            return response()->json([
                'success' => true,
                'message' => 'Meeting ended successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to end meeting: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get meeting participants
     */
    public function participants(MeetingCalendarIntegration $meeting): JsonResponse
    {
        try {
            $participants = $this->meetingService->getMeetingParticipants($meeting);

            return response()->json([
                'success' => true,
                'participants' => $participants,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get participants: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update meeting settings
     */
    public function updateSettings(MeetingCalendarIntegration $meeting, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'settings' => 'required|array',
            'settings.audio_enabled' => 'boolean',
            'settings.video_enabled' => 'boolean',
            'settings.screen_sharing_enabled' => 'boolean',
            'settings.chat_enabled' => 'boolean',
            'settings.waiting_room_enabled' => 'boolean',
            'settings.max_participants' => 'integer|min:2|max:100',
            'settings.recording_enabled' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $settings = $request->input('settings');
            $this->meetingService->updateMeetingSettings($meeting, $settings);

            return response()->json([
                'success' => true,
                'message' => 'Meeting settings updated successfully',
                'meeting' => $meeting->fresh()->getMeetingInfo(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update settings: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancel a meeting
     */
    public function cancel(MeetingCalendarIntegration $meeting, Request $request): JsonResponse
    {
        try {
            $reason = $request->input('reason');
            $meeting->cancelMeeting($reason);

            return response()->json([
                'success' => true,
                'message' => 'Meeting cancelled successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel meeting: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List meetings for a user
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $status = $request->input('status');
        $limit = $request->input('limit', 20);

        $query = MeetingCalendarIntegration::whereHas('attendees', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->with(['calendarEvent', 'attendees.user']);

        if ($status) {
            $query->where('status', $status);
        }

        // Order by calendar event start time
        $query->join('calendar_events', 'meeting_calendar_integrations.calendar_event_id', '=', 'calendar_events.id')
            ->orderBy('calendar_events.starts_at', 'desc')
            ->select('meeting_calendar_integrations.*');

        $meetings = $query->paginate($limit);

        return response()->json([
            'success' => true,
            'meetings' => $meetings->getCollection()->map(fn($meeting) => $meeting->getMeetingInfo()),
            'pagination' => [
                'current_page' => $meetings->currentPage(),
                'last_page' => $meetings->lastPage(),
                'per_page' => $meetings->perPage(),
                'total' => $meetings->total(),
            ],
        ]);
    }

    /**
     * Get upcoming meetings
     */
    public function upcoming(Request $request): JsonResponse
    {
        $user = $request->user();
        $limit = $request->input('limit', 10);

        $meetings = MeetingCalendarIntegration::whereHas('attendees', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })
        ->whereIn('status', ['scheduled', 'active'])
        ->whereHas('calendarEvent', function ($q) {
            $q->where('starts_at', '>', now());
        })
        ->with(['calendarEvent', 'attendees.user'])
        ->join('calendar_events', 'meeting_calendar_integrations.calendar_event_id', '=', 'calendar_events.id')
        ->orderBy('calendar_events.starts_at', 'asc')
        ->select('meeting_calendar_integrations.*')
        ->limit($limit)
        ->get();

        return response()->json([
            'success' => true,
            'meetings' => $meetings->map(fn($meeting) => $meeting->getMeetingInfo()),
        ]);
    }

    /**
     * Add attendee to meeting
     */
    public function addAttendee(MeetingCalendarIntegration $meeting, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'name' => 'string|nullable',
            'role' => 'in:attendee,presenter,co-host,host',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $attendee = $meeting->addAttendee(
                $request->email,
                $request->input('name'),
                $request->input('role', 'attendee')
            );

            return response()->json([
                'success' => true,
                'message' => 'Attendee added successfully',
                'attendee' => $attendee->getAttendeeInfo(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add attendee: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove attendee from meeting
     */
    public function removeAttendee(MeetingCalendarIntegration $meeting, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'attendee_id' => 'required|exists:meeting_attendees,id',
            'reason' => 'string|nullable',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $attendee = $meeting->attendees()->findOrFail($request->attendee_id);
            $attendee->removFromMeeting($request->input('reason'));

            return response()->json([
                'success' => true,
                'message' => 'Attendee removed successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove attendee: ' . $e->getMessage(),
            ], 500);
        }
    }
}