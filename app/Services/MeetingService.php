<?php

namespace App\Services;

use App\Models\CalendarEvent;
use App\Models\MeetingAttendee;
use App\Models\MeetingCalendarIntegration;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MeetingService
{
    public function __construct(
        protected LiveKitService $liveKitService
    ) {}

    /**
     * Create a meeting from a calendar event
     */
    public function createMeetingFromCalendarEvent(
        CalendarEvent $calendarEvent,
        array $meetingSettings = []
    ): MeetingCalendarIntegration {
        // Generate unique meeting ID and room name
        $meetingId = Str::uuid();
        $roomName = "meeting_{$calendarEvent->id}_{$meetingId}";

        // Default meeting settings
        $defaultSettings = [
            'audio_enabled' => true,
            'video_enabled' => true,
            'screen_sharing_enabled' => true,
            'chat_enabled' => true,
            'waiting_room_enabled' => false,
            'lobby_enabled' => true,
            'mute_on_entry' => false,
            'camera_on_entry' => true,
            'max_participants' => 50,
            'recording_auto_start' => false,
            'recording_layout' => 'grid',
        ];

        $settings = array_merge($defaultSettings, $meetingSettings);

        // Participant limits
        $participantLimits = [
            'max_participants' => $settings['max_participants'],
            'waiting_room_enabled' => $settings['waiting_room_enabled'],
            'lobby_enabled' => $settings['lobby_enabled'],
        ];

        // Create meeting integration
        $meetingIntegration = MeetingCalendarIntegration::create([
            'calendar_event_id' => $calendarEvent->id,
            'meeting_type' => $calendarEvent->isRecurring() ? 'recurring' : 'scheduled',
            'meeting_provider' => 'livekit',
            'meeting_id' => $meetingId,
            'meeting_settings' => $settings,
            'participant_limits' => $participantLimits,
            'auto_join_enabled' => $settings['auto_join_enabled'] ?? false,
            'recording_enabled' => $settings['recording_enabled'] ?? false,
            'e2ee_enabled' => $settings['e2ee_enabled'] ?? true,
            'status' => 'scheduled',
            'participant_roster' => $calendarEvent->attendees ?? [],
        ]);

        // Create LiveKit room
        try {
            $roomMetadata = [
                'meeting_id' => $meetingId,
                'calendar_event_id' => $calendarEvent->id,
                'event_title' => $calendarEvent->title,
                'scheduled_start' => $calendarEvent->starts_at->toISOString(),
                'scheduled_end' => $calendarEvent->ends_at?->toISOString(),
                'e2ee_enabled' => $meetingIntegration->e2ee_enabled,
                'recording_enabled' => $meetingIntegration->recording_enabled,
            ];

            if ($meetingIntegration->e2ee_enabled) {
                $encryptionKey = $this->generateEncryptionKey();
                $room = $this->liveKitService->createE2EERoom(
                    $roomName,
                    $this->getParticipantIdentities($calendarEvent),
                    $encryptionKey,
                    $roomMetadata
                );
            } else {
                $room = $this->liveKitService->createRoom(
                    $roomName,
                    $settings['max_participants'],
                    $this->calculateEmptyTimeout($calendarEvent),
                    $roomMetadata
                );
            }

            // Update meeting integration with LiveKit room info
            $meetingIntegration->update([
                'integration_metadata' => [
                    'livekit_room_name' => $roomName,
                    'livekit_room_sid' => $room['sid'] ?? null,
                    'encryption_key_id' => $encryptionKey ?? null,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to create LiveKit room for meeting', [
                'meeting_id' => $meetingId,
                'calendar_event_id' => $calendarEvent->id,
                'error' => $e->getMessage(),
            ]);

            throw new \Exception('Failed to create meeting room: ' . $e->getMessage());
        }

        // Add attendees from calendar event
        $this->addAttendeesFromCalendarEvent($meetingIntegration, $calendarEvent);

        // Set up reminders
        $this->setupMeetingReminders($meetingIntegration);

        return $meetingIntegration;
    }

    /**
     * Start a scheduled meeting
     */
    public function startMeeting(MeetingCalendarIntegration $meeting): array
    {
        if (!$meeting->isScheduled()) {
            throw new \Exception('Meeting is not in scheduled state');
        }

        $meeting->startMeeting();

        // Ensure LiveKit room is ready
        $roomName = $meeting->integration_metadata['livekit_room_name'];
        $room = $this->liveKitService->getRoom($roomName);
        
        if (!$room) {
            // Recreate room if it doesn't exist
            $this->recreateLiveKitRoom($meeting);
        }

        return [
            'meeting' => $meeting->fresh(),
            'join_url' => $meeting->generateJoinUrl(),
            'host_url' => $meeting->generateHostUrl(),
        ];
    }

    /**
     * Join a meeting
     */
    public function joinMeeting(
        MeetingCalendarIntegration $meeting,
        User $user,
        array $permissions = []
    ): array {
        // Find or create attendee record
        $attendee = MeetingAttendee::where('meeting_integration_id', $meeting->id)
            ->where('user_id', $user->id)
            ->first();

        if (!$attendee) {
            $attendee = $meeting->addAttendee(
                $user->email,
                $user->name,
                'attendee'
            );
            $attendee->user_id = $user->id;
            $attendee->save();
        }

        // Mark attendee as joined
        $attendee->joinMeeting();

        // Generate LiveKit access token
        $roomName = $meeting->integration_metadata['livekit_room_name'];
        $participantIdentity = "user_{$user->id}_" . Str::random(8);
        
        $defaultPermissions = [
            'canPublish' => true,
            'canSubscribe' => true,
            'canPublishData' => true,
            'canUpdateOwnMetadata' => true,
            'hidden' => false,
        ];

        // Adjust permissions based on attendee role
        if ($attendee->canManageMeeting()) {
            $defaultPermissions['roomAdmin'] = true;
            $defaultPermissions['roomRecord'] = $meeting->recording_enabled;
        }

        $grants = array_merge($defaultPermissions, $permissions);

        $accessToken = $this->liveKitService->generateAccessToken(
            $roomName,
            $participantIdentity,
            $user->name,
            $grants
        );

        $connectionDetails = [
            'server_url' => config('livekit.server_url'),
            'access_token' => $accessToken,
            'room_name' => $roomName,
            'participant_identity' => $participantIdentity,
            'participant_name' => $user->name,
            'meeting_settings' => $meeting->meeting_settings,
            'e2ee_enabled' => $meeting->e2ee_enabled,
        ];

        // Add E2EE settings if enabled
        if ($meeting->e2ee_enabled && isset($meeting->integration_metadata['encryption_key_id'])) {
            $connectionDetails['encryption_key_id'] = $meeting->integration_metadata['encryption_key_id'];
        }

        return $connectionDetails;
    }

    /**
     * End a meeting
     */
    public function endMeeting(MeetingCalendarIntegration $meeting): void
    {
        $meeting->endMeeting();

        // Delete LiveKit room
        $roomName = $meeting->integration_metadata['livekit_room_name'];
        $this->liveKitService->deleteRoom($roomName);

        Log::info('Meeting ended', [
            'meeting_id' => $meeting->id,
            'duration_minutes' => $meeting->getMeetingDuration(),
            'attendee_count' => $meeting->getAttendeeCount(),
        ]);
    }

    /**
     * Get meeting participants
     */
    public function getMeetingParticipants(MeetingCalendarIntegration $meeting): array
    {
        $roomName = $meeting->integration_metadata['livekit_room_name'];
        $liveKitParticipants = $this->liveKitService->listParticipants($roomName);

        return [
            'total_attendees' => $meeting->getAttendeeCount(),
            'active_participants' => count($liveKitParticipants),
            'attendees' => $meeting->attendees->map(fn($attendee) => $attendee->getAttendeeInfo()),
            'livekit_participants' => $liveKitParticipants,
        ];
    }

    /**
     * Update meeting settings
     */
    public function updateMeetingSettings(
        MeetingCalendarIntegration $meeting,
        array $settings
    ): void {
        $meeting->updateMeetingSettings($settings);

        // Update LiveKit room metadata if needed
        $roomName = $meeting->integration_metadata['livekit_room_name'];
        $this->liveKitService->updateRoomMetadata($roomName, [
            'settings_updated_at' => now()->toISOString(),
            'settings' => $settings,
        ]);
    }

    /**
     * Generate join URL for a meeting
     */
    public function generateJoinUrl(MeetingCalendarIntegration $meeting): string
    {
        return route('meetings.join', ['meeting' => $meeting->id]);
    }

    /**
     * Generate host URL for a meeting
     */
    public function generateHostUrl(MeetingCalendarIntegration $meeting): string
    {
        return route('meetings.host', ['meeting' => $meeting->id]);
    }

    /**
     * Private helper methods
     */
    private function generateEncryptionKey(): string
    {
        return base64_encode(random_bytes(32));
    }

    private function getParticipantIdentities(CalendarEvent $calendarEvent): array
    {
        $identities = [];
        
        if ($calendarEvent->attendees) {
            foreach ($calendarEvent->attendees as $attendee) {
                if (isset($attendee['email'])) {
                    $identities[] = "attendee_{$attendee['email']}_" . Str::random(8);
                }
            }
        }

        return $identities;
    }

    private function calculateEmptyTimeout(CalendarEvent $calendarEvent): int
    {
        $duration = $calendarEvent->getDurationInMinutes();
        
        // Set empty timeout to 30 minutes after scheduled end, or 60 minutes minimum
        return max(($duration + 30) * 60, 3600);
    }

    private function addAttendeesFromCalendarEvent(
        MeetingCalendarIntegration $meeting,
        CalendarEvent $calendarEvent
    ): void {
        if (!$calendarEvent->attendees) {
            return;
        }

        foreach ($calendarEvent->attendees as $attendeeData) {
            $email = $attendeeData['email'] ?? null;
            $name = $attendeeData['name'] ?? null;
            $role = $attendeeData['role'] ?? 'attendee';

            if ($email) {
                $meeting->addAttendee($email, $name, $role);
            }
        }
    }

    private function setupMeetingReminders(MeetingCalendarIntegration $meeting): void
    {
        $calendarEvent = $meeting->calendarEvent;
        
        // Create default reminders: 15 minutes and 5 minutes before
        $reminderTimes = [15, 5];

        foreach ($reminderTimes as $minutesBefore) {
            // Add reminders for all attendees who are users
            $meeting->attendees()
                ->whereNotNull('user_id')
                ->with('user')
                ->get()
                ->each(function ($attendee) use ($meeting, $minutesBefore) {
                    if ($attendee->user) {
                        $meeting->addReminder($attendee->user, $minutesBefore, 'notification');
                    }
                });
        }
    }

    private function recreateLiveKitRoom(MeetingCalendarIntegration $meeting): void
    {
        $roomName = $meeting->integration_metadata['livekit_room_name'];
        
        $roomMetadata = [
            'meeting_id' => $meeting->meeting_id,
            'calendar_event_id' => $meeting->calendar_event_id,
            'recreated_at' => now()->toISOString(),
        ];

        if ($meeting->e2ee_enabled) {
            $encryptionKey = $this->generateEncryptionKey();
            $this->liveKitService->createE2EERoom(
                $roomName,
                [],
                $encryptionKey,
                $roomMetadata
            );
        } else {
            $this->liveKitService->createRoom(
                $roomName,
                $meeting->participant_limits['max_participants'] ?? 50,
                1800, // 30 minutes
                $roomMetadata
            );
        }
    }
}