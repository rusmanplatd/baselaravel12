<?php

namespace App\Http\Controllers\Api\Chat;

use App\Http\Controllers\Controller;
use App\Models\Chat\Conversation;
use App\Models\Chat\VideoCall;
use App\Services\LiveKitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class VideoCallController extends Controller
{
    protected LiveKitService $liveKitService;

    public function __construct(LiveKitService $liveKitService)
    {
        $this->middleware('auth:api');
        $this->middleware('throttle:30,1');
        
        // Apply chat permissions
        $this->middleware('chat.permission:chat.calls.initiate,conversationId')->only(['initiate']);
        $this->middleware('chat.permission:chat.calls.join,conversationId')->only(['join']);
        $this->middleware('chat.permission:chat.calls.moderate,conversationId')->only(['end', 'mute', 'kick']);
        
        $this->liveKitService = $liveKitService;
    }

    /**
     * Initiate a video/audio call
     */
    public function initiate(Request $request, string $conversationId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'call_type' => 'required|string|in:video,audio',
            'enable_recording' => 'boolean',
            'enable_e2ee' => 'boolean',
            'quality_preset' => 'string|in:low,medium,high',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors(),
            ], 422);
        }

        $user = Auth::user();
        $conversation = Conversation::find($conversationId);

        if (! $conversation) {
            return response()->json(['error' => 'Conversation not found'], 404);
        }

        // Check if user is participant in conversation
        if (! $conversation->participants()->where('user_id', $user->id)->exists()) {
            return response()->json(['error' => 'You are not a participant in this conversation'], 403);
        }

        // Check if there's already an active call
        $activeCall = $conversation->videoCalls()->active()->first();
        if ($activeCall) {
            return response()->json([
                'error' => 'There is already an active call in this conversation',
                'active_call_id' => $activeCall->id,
            ], 409);
        }

        $data = $validator->validated();
        $roomName = 'call_'.$conversationId.'_'.Str::random(8);

        // Get conversation participants
        $participants = $conversation->participants()->with('user')->get();
        $participantIdentities = [];

        try {
            // Create LiveKit room
            $e2eeEnabled = $data['enable_e2ee'] ?? config('livekit.e2ee.enabled');

            if ($e2eeEnabled) {
                $encryptionKey = Str::random(32);
                $liveKitRoom = $this->liveKitService->createE2EERoom(
                    $roomName,
                    $participants->pluck('user.id')->toArray(),
                    $encryptionKey
                );
            } else {
                $liveKitRoom = $this->liveKitService->createRoom($roomName, $participants->count() + 2);
            }

            // Create video call record
            $videoCall = VideoCall::create([
                'conversation_id' => $conversationId,
                'initiated_by' => $user->id,
                'livekit_room_name' => $roomName,
                'call_type' => $data['call_type'],
                'status' => 'initiated',
                'participants' => $participants->pluck('user_id')->toArray(),
                'e2ee_settings' => $e2eeEnabled ? [
                    'enabled' => true,
                    'algorithm' => 'AES-GCM',
                    'key_rotation_enabled' => true,
                ] : null,
                'quality_settings' => json_encode([
                    'preset' => $data['quality_preset'] ?? 'medium',
                    'enable_simulcast' => true,
                ]),
                'is_recorded' => $data['enable_recording'] ?? false,
                'metadata' => [
                    'room_name' => $roomName,
                    'livekit_room' => $liveKitRoom,
                ],
            ]);

            // Add participants
            foreach ($participants as $participant) {
                $participantIdentity = 'user_'.$participant->user_id.'_'.Str::random(6);
                $participantIdentities[$participant->user_id] = $participantIdentity;

                $videoCall->addParticipant($participant->user, $participantIdentity);
            }

            // Generate access token for initiator
            $initiatorIdentity = $participantIdentities[$user->id];
            $connectionDetails = $this->liveKitService->getConnectionDetails(
                $roomName,
                $initiatorIdentity,
                $user->name,
                [
                    'canPublish' => true,
                    'canSubscribe' => true,
                    'canPublishData' => true,
                    'roomAdmin' => true,
                ]
            );

            return response()->json([
                'call' => $videoCall->fresh(['callParticipants.user']),
                'connection_details' => $connectionDetails,
                'participant_identities' => $participantIdentities,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create video call',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Join an existing call
     */
    public function join(Request $request, string $callId): JsonResponse
    {
        $user = Auth::user();
        $videoCall = VideoCall::with(['callParticipants', 'conversation.participants'])
            ->find($callId);

        if (! $videoCall) {
            return response()->json(['error' => 'Video call not found'], 404);
        }

        // Check if user can join this call
        $participant = $videoCall->callParticipants()
            ->where('user_id', $user->id)
            ->first();

        if (! $participant) {
            return response()->json(['error' => 'You are not invited to this call'], 403);
        }

        if ($participant->status === 'joined') {
            return response()->json(['error' => 'You are already in this call'], 409);
        }

        try {
            // Generate access token
            $connectionDetails = $this->liveKitService->getConnectionDetails(
                $videoCall->livekit_room_name,
                $participant->participant_identity,
                $user->name,
                [
                    'canPublish' => true,
                    'canSubscribe' => true,
                    'canPublishData' => true,
                ]
            );

            // Update participant status
            $participant->joinCall();

            // If this is the first participant joining, start the call
            if ($videoCall->status === 'initiated') {
                $videoCall->startCall();
            }

            return response()->json([
                'call' => $videoCall->fresh(['callParticipants.user']),
                'connection_details' => $connectionDetails,
                'participant' => $participant->fresh(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to join video call',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Leave a call
     */
    public function leave(Request $request, string $callId): JsonResponse
    {
        $user = Auth::user();
        $videoCall = VideoCall::find($callId);

        if (! $videoCall) {
            return response()->json(['error' => 'Video call not found'], 404);
        }

        $participant = $videoCall->callParticipants()
            ->where('user_id', $user->id)
            ->first();

        if (! $participant) {
            return response()->json(['error' => 'You are not in this call'], 404);
        }

        try {
            // Remove participant from LiveKit room
            $this->liveKitService->removeParticipant(
                $videoCall->livekit_room_name,
                $participant->participant_identity
            );

            // Update participant status
            $participant->leaveCall();

            // Check if all participants have left
            $activeParticipants = $videoCall->callParticipants()
                ->where('status', 'joined')
                ->count();

            if ($activeParticipants === 0) {
                $videoCall->endCall('all_participants_left');

                // Delete the LiveKit room
                $this->liveKitService->deleteRoom($videoCall->livekit_room_name);
            }

            return response()->json([
                'message' => 'Left call successfully',
                'call' => $videoCall->fresh(['callParticipants.user']),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to leave video call',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reject a call invitation
     */
    public function reject(Request $request, string $callId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors(),
            ], 422);
        }

        $user = Auth::user();
        $videoCall = VideoCall::find($callId);

        if (! $videoCall) {
            return response()->json(['error' => 'Video call not found'], 404);
        }

        $participant = $videoCall->callParticipants()
            ->where('user_id', $user->id)
            ->first();

        if (! $participant) {
            return response()->json(['error' => 'You are not invited to this call'], 404);
        }

        $data = $validator->validated();
        $participant->rejectCall($data['reason'] ?? null);

        return response()->json([
            'message' => 'Call rejected successfully',
            'participant' => $participant->fresh(),
        ]);
    }

    /**
     * End a call (only call initiator or admin)
     */
    public function end(Request $request, string $callId): JsonResponse
    {
        $user = Auth::user();
        $videoCall = VideoCall::find($callId);

        if (! $videoCall) {
            return response()->json(['error' => 'Video call not found'], 404);
        }

        // Only initiator or admin can end call
        if ($videoCall->initiated_by !== $user->id && ! $user->can('moderate_chat')) {
            return response()->json(['error' => 'You do not have permission to end this call'], 403);
        }

        try {
            // End the call
            $videoCall->endCall('ended_by_user');

            // Remove all participants from LiveKit room
            $participants = $this->liveKitService->listParticipants($videoCall->livekit_room_name);
            foreach ($participants as $participant) {
                $this->liveKitService->removeParticipant(
                    $videoCall->livekit_room_name,
                    $participant['identity']
                );
            }

            // Delete the LiveKit room
            $this->liveKitService->deleteRoom($videoCall->livekit_room_name);

            return response()->json([
                'message' => 'Call ended successfully',
                'call' => $videoCall->fresh(['callParticipants.user']),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to end video call',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get call status and details
     */
    public function show(string $callId): JsonResponse
    {
        $user = Auth::user();
        $videoCall = VideoCall::with([
            'callParticipants.user',
            'conversation',
            'events' => fn ($query) => $query->orderBy('event_timestamp', 'desc')->limit(50),
            'qualityMetrics' => fn ($query) => $query->orderBy('measured_at', 'desc')->limit(10),
        ])->find($callId);

        if (! $videoCall) {
            return response()->json(['error' => 'Video call not found'], 404);
        }

        // Check if user has access to this call
        $hasAccess = $videoCall->conversation->participants()
            ->where('user_id', $user->id)
            ->exists();

        if (! $hasAccess) {
            return response()->json(['error' => 'You do not have access to this call'], 403);
        }

        return response()->json([
            'call' => $videoCall,
            'summary' => $videoCall->getCallSummary(),
        ]);
    }

    /**
     * Get call history for a conversation
     */
    public function history(Request $request, string $conversationId): JsonResponse
    {
        $user = Auth::user();
        $conversation = Conversation::find($conversationId);

        if (! $conversation) {
            return response()->json(['error' => 'Conversation not found'], 404);
        }

        // Check if user is participant
        if (! $conversation->participants()->where('user_id', $user->id)->exists()) {
            return response()->json(['error' => 'You are not a participant in this conversation'], 403);
        }

        $calls = $conversation->videoCalls()
            ->with(['callParticipants.user', 'initiator'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'calls' => $calls->items(),
            'pagination' => [
                'current_page' => $calls->currentPage(),
                'per_page' => $calls->perPage(),
                'total' => $calls->total(),
                'last_page' => $calls->lastPage(),
            ],
        ]);
    }

    /**
     * Update call quality metrics
     */
    public function updateQualityMetrics(Request $request, string $callId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'participant_identity' => 'required|string',
            'video_metrics' => 'nullable|array',
            'audio_metrics' => 'nullable|array',
            'connection_metrics' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors(),
            ], 422);
        }

        $user = Auth::user();
        $videoCall = VideoCall::find($callId);

        if (! $videoCall) {
            return response()->json(['error' => 'Video call not found'], 404);
        }

        $participant = $videoCall->callParticipants()
            ->where('participant_identity', $request->input('participant_identity'))
            ->where('user_id', $user->id)
            ->first();

        if (! $participant) {
            return response()->json(['error' => 'Participant not found'], 404);
        }

        $data = $validator->validated();

        $qualityMetric = $participant->qualityMetrics()->create([
            'video_call_id' => $videoCall->id,
            'measured_at' => now(),
            'video_metrics' => $data['video_metrics'],
            'audio_metrics' => $data['audio_metrics'],
            'connection_metrics' => $data['connection_metrics'],
        ]);

        $qualityMetric->updateQualityScore();

        return response()->json([
            'message' => 'Quality metrics updated successfully',
            'metric' => $qualityMetric->fresh(),
        ]);
    }
}
