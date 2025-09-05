<?php

namespace App\Http\Controllers\Api\Chat;

use App\Http\Controllers\Controller;
use App\Models\Chat\VideoCall;
use App\Services\LiveKitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LiveKitWebhookController extends Controller
{
    protected LiveKitService $liveKitService;

    public function __construct(LiveKitService $liveKitService)
    {
        $this->liveKitService = $liveKitService;
    }

    /**
     * Handle LiveKit webhook events
     */
    public function handle(Request $request): JsonResponse
    {
        // Validate webhook signature
        $body = $request->getContent();
        $signature = $request->header('lk-signature');
        $timestamp = $request->header('lk-timestamp');

        if (! $signature || ! $timestamp) {
            Log::warning('LiveKit webhook missing required headers');

            return response()->json(['error' => 'Missing headers'], 400);
        }

        if (! $this->liveKitService->validateWebhook($body, $signature, $timestamp)) {
            Log::warning('LiveKit webhook signature validation failed');

            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $payload = $request->json()->all();
        $event = $payload['event'] ?? null;

        if (! $event) {
            Log::warning('LiveKit webhook missing event data');

            return response()->json(['error' => 'Missing event data'], 400);
        }

        try {
            $this->processEvent($event, $payload);

            return response()->json(['message' => 'Webhook processed successfully']);

        } catch (\Exception $e) {
            Log::error('Error processing LiveKit webhook', [
                'event' => $event,
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);

            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Process individual webhook events
     */
    protected function processEvent(string $eventType, array $payload): void
    {
        $roomName = $payload['room']['name'] ?? null;

        if (! $roomName) {
            Log::warning('LiveKit webhook missing room name', ['payload' => $payload]);

            return;
        }

        $videoCall = VideoCall::where('livekit_room_name', $roomName)->first();

        if (! $videoCall) {
            Log::warning('Video call not found for LiveKit room', ['room_name' => $roomName]);

            return;
        }

        switch ($eventType) {
            case 'room_started':
                $this->handleRoomStarted($videoCall, $payload);
                break;

            case 'room_finished':
                $this->handleRoomFinished($videoCall, $payload);
                break;

            case 'participant_joined':
                $this->handleParticipantJoined($videoCall, $payload);
                break;

            case 'participant_left':
                $this->handleParticipantLeft($videoCall, $payload);
                break;

            case 'track_published':
                $this->handleTrackPublished($videoCall, $payload);
                break;

            case 'track_unpublished':
                $this->handleTrackUnpublished($videoCall, $payload);
                break;

            case 'recording_started':
                $this->handleRecordingStarted($videoCall, $payload);
                break;

            case 'recording_finished':
                $this->handleRecordingFinished($videoCall, $payload);
                break;

            default:
                Log::info('Unhandled LiveKit webhook event', [
                    'event' => $eventType,
                    'room' => $roomName,
                ]);
        }
    }

    /**
     * Handle room started event
     */
    protected function handleRoomStarted(VideoCall $videoCall, array $payload): void
    {
        if ($videoCall->status !== 'active') {
            $videoCall->startCall();
        }

        $videoCall->events()->create([
            'event_type' => 'room_started',
            'event_data' => [
                'room' => $payload['room'],
                'timestamp' => now()->toISOString(),
            ],
            'event_timestamp' => now(),
        ]);

        Log::info('LiveKit room started', [
            'call_id' => $videoCall->id,
            'room_name' => $videoCall->livekit_room_name,
        ]);
    }

    /**
     * Handle room finished event
     */
    protected function handleRoomFinished(VideoCall $videoCall, array $payload): void
    {
        if ($videoCall->status === 'active') {
            $videoCall->endCall('room_finished');
        }

        $videoCall->events()->create([
            'event_type' => 'room_finished',
            'event_data' => [
                'room' => $payload['room'],
                'timestamp' => now()->toISOString(),
            ],
            'event_timestamp' => now(),
        ]);

        Log::info('LiveKit room finished', [
            'call_id' => $videoCall->id,
            'room_name' => $videoCall->livekit_room_name,
        ]);
    }

    /**
     * Handle participant joined event
     */
    protected function handleParticipantJoined(VideoCall $videoCall, array $payload): void
    {
        $participantData = $payload['participant'] ?? null;

        if (! $participantData) {
            return;
        }

        $participantIdentity = $participantData['identity'];

        $participant = $videoCall->callParticipants()
            ->where('participant_identity', $participantIdentity)
            ->first();

        if ($participant && $participant->status !== 'joined') {
            $participant->joinCall();

            // Update device info if available
            if (isset($participantData['metadata'])) {
                $metadata = json_decode($participantData['metadata'], true);
                if (isset($metadata['device_info'])) {
                    $participant->updateDeviceInfo($metadata['device_info']);
                }
            }
        }

        $videoCall->events()->create([
            'user_id' => $participant?->user_id,
            'event_type' => 'participant_joined',
            'event_data' => [
                'participant' => $participantData,
                'timestamp' => now()->toISOString(),
            ],
            'event_timestamp' => now(),
        ]);

        Log::info('Participant joined LiveKit room', [
            'call_id' => $videoCall->id,
            'participant_identity' => $participantIdentity,
            'user_id' => $participant?->user_id,
        ]);
    }

    /**
     * Handle participant left event
     */
    protected function handleParticipantLeft(VideoCall $videoCall, array $payload): void
    {
        $participantData = $payload['participant'] ?? null;

        if (! $participantData) {
            return;
        }

        $participantIdentity = $participantData['identity'];

        $participant = $videoCall->callParticipants()
            ->where('participant_identity', $participantIdentity)
            ->first();

        if ($participant && $participant->status === 'joined') {
            $participant->leaveCall();
        }

        $videoCall->events()->create([
            'user_id' => $participant?->user_id,
            'event_type' => 'participant_left',
            'event_data' => [
                'participant' => $participantData,
                'timestamp' => now()->toISOString(),
            ],
            'event_timestamp' => now(),
        ]);

        // Check if all participants have left
        $activeParticipants = $videoCall->callParticipants()
            ->where('status', 'joined')
            ->count();

        if ($activeParticipants === 0 && $videoCall->status === 'active') {
            $videoCall->endCall('all_participants_left');
        }

        Log::info('Participant left LiveKit room', [
            'call_id' => $videoCall->id,
            'participant_identity' => $participantIdentity,
            'user_id' => $participant?->user_id,
        ]);
    }

    /**
     * Handle track published event
     */
    protected function handleTrackPublished(VideoCall $videoCall, array $payload): void
    {
        $participantData = $payload['participant'] ?? null;
        $trackData = $payload['track'] ?? null;

        if (! $participantData || ! $trackData) {
            return;
        }

        $participantIdentity = $participantData['identity'];

        $participant = $videoCall->callParticipants()
            ->where('participant_identity', $participantIdentity)
            ->first();

        if ($participant) {
            $mediaTracks = $participant->media_tracks ?? [];
            $mediaTracks[$trackData['type']] = [
                'sid' => $trackData['sid'],
                'name' => $trackData['name'],
                'muted' => $trackData['muted'] ?? false,
                'published_at' => now()->toISOString(),
            ];

            $participant->updateMediaTracks($mediaTracks);
        }

        $videoCall->events()->create([
            'user_id' => $participant?->user_id,
            'event_type' => 'track_published',
            'event_data' => [
                'participant' => $participantData,
                'track' => $trackData,
                'timestamp' => now()->toISOString(),
            ],
            'event_timestamp' => now(),
        ]);

        Log::info('Track published in LiveKit room', [
            'call_id' => $videoCall->id,
            'participant_identity' => $participantIdentity,
            'track_type' => $trackData['type'],
            'track_sid' => $trackData['sid'],
        ]);
    }

    /**
     * Handle track unpublished event
     */
    protected function handleTrackUnpublished(VideoCall $videoCall, array $payload): void
    {
        $participantData = $payload['participant'] ?? null;
        $trackData = $payload['track'] ?? null;

        if (! $participantData || ! $trackData) {
            return;
        }

        $participantIdentity = $participantData['identity'];

        $participant = $videoCall->callParticipants()
            ->where('participant_identity', $participantIdentity)
            ->first();

        if ($participant) {
            $mediaTracks = $participant->media_tracks ?? [];
            unset($mediaTracks[$trackData['type']]);

            $participant->updateMediaTracks($mediaTracks);
        }

        $videoCall->events()->create([
            'user_id' => $participant?->user_id,
            'event_type' => 'track_unpublished',
            'event_data' => [
                'participant' => $participantData,
                'track' => $trackData,
                'timestamp' => now()->toISOString(),
            ],
            'event_timestamp' => now(),
        ]);

        Log::info('Track unpublished in LiveKit room', [
            'call_id' => $videoCall->id,
            'participant_identity' => $participantIdentity,
            'track_type' => $trackData['type'],
            'track_sid' => $trackData['sid'],
        ]);
    }

    /**
     * Handle recording started event
     */
    protected function handleRecordingStarted(VideoCall $videoCall, array $payload): void
    {
        $recordingData = $payload['egressInfo'] ?? null;

        if (! $recordingData) {
            return;
        }

        $videoCall->recordings()->create([
            'recording_id' => $recordingData['egressId'],
            'storage_type' => 's3', // Default to S3
            'file_path' => $recordingData['fileResults'][0]['filename'] ?? 'unknown',
            'file_format' => 'mp4',
            'recording_started_at' => now(),
            'processing_status' => 'recording',
        ]);

        $videoCall->update(['is_recorded' => true]);

        $videoCall->events()->create([
            'event_type' => 'recording_started',
            'event_data' => [
                'recording' => $recordingData,
                'timestamp' => now()->toISOString(),
            ],
            'event_timestamp' => now(),
        ]);

        Log::info('Recording started for LiveKit room', [
            'call_id' => $videoCall->id,
            'recording_id' => $recordingData['egressId'],
        ]);
    }

    /**
     * Handle recording finished event
     */
    protected function handleRecordingFinished(VideoCall $videoCall, array $payload): void
    {
        $recordingData = $payload['egressInfo'] ?? null;

        if (! $recordingData) {
            return;
        }

        $recording = $videoCall->recordings()
            ->where('recording_id', $recordingData['egressId'])
            ->first();

        if ($recording) {
            $fileResult = $recordingData['fileResults'][0] ?? null;

            $recording->update([
                'recording_ended_at' => now(),
                'processing_status' => 'completed',
                'file_size' => $fileResult['size'] ?? null,
                'duration_seconds' => $fileResult['duration'] ?? null,
                'recording_metadata' => $recordingData,
            ]);
        }

        $videoCall->events()->create([
            'event_type' => 'recording_finished',
            'event_data' => [
                'recording' => $recordingData,
                'timestamp' => now()->toISOString(),
            ],
            'event_timestamp' => now(),
        ]);

        Log::info('Recording finished for LiveKit room', [
            'call_id' => $videoCall->id,
            'recording_id' => $recordingData['egressId'],
        ]);
    }
}
