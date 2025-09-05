<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chat\Message;
use App\Models\VoiceTranscription;
use App\Services\VoiceTranscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class VoiceTranscriptionController extends Controller
{
    public function __construct(
        private VoiceTranscriptionService $transcriptionService
    ) {
        $this->middleware('auth:api');
        $this->middleware('throttle:30,1');
    }

    /**
     * Transcribe voice message
     */
    public function transcribe(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'message_id' => 'required|string|exists:messages,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $organizationId = $request->header('X-Organization-Id');
        
        $message = Message::where('id', $request->message_id)
            ->whereHas('conversation', function ($query) use ($organizationId) {
                $query->where('organization_id', $organizationId);
            })
            ->with('attachments')
            ->firstOrFail();

        // Check if user has access to this conversation
        if (!$message->conversation->hasParticipant($request->user()->id)) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        try {
            $transcription = $this->transcriptionService->transcribeVoiceMessage($message);
            
            if (!$transcription) {
                return response()->json(['error' => 'Message is not a voice message'], 400);
            }

            return response()->json([
                'transcription' => $this->formatTranscriptionResponse($transcription),
                'message' => 'Transcription started successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Transcription failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get transcription status
     */
    public function status(Request $request, string $messageId): JsonResponse
    {
        $organizationId = $request->header('X-Organization-Id');
        
        $message = Message::where('id', $messageId)
            ->whereHas('conversation', function ($query) use ($organizationId) {
                $query->where('organization_id', $organizationId);
            })
            ->firstOrFail();

        // Check if user has access to this conversation
        if (!$message->conversation->hasParticipant($request->user()->id)) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        $status = $this->transcriptionService->getTranscriptionStatus($message);
        
        if (!$status) {
            return response()->json(['error' => 'Message is not a voice message'], 400);
        }

        $transcription = VoiceTranscription::where('message_id', $messageId)->first();

        return response()->json([
            'status' => $status,
            'transcription' => $transcription ? $this->formatTranscriptionResponse($transcription) : null,
        ]);
    }

    /**
     * Get transcription details
     */
    public function show(Request $request, string $transcriptionId): JsonResponse
    {
        $organizationId = $request->header('X-Organization-Id');
        
        $transcription = VoiceTranscription::where('id', $transcriptionId)
            ->whereHas('message.conversation', function ($query) use ($organizationId) {
                $query->where('organization_id', $organizationId);
            })
            ->with(['message', 'attachment'])
            ->firstOrFail();

        // Check if user has access to this conversation
        if (!$transcription->message->conversation->hasParticipant($request->user()->id)) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        return response()->json([
            'transcription' => $this->formatTranscriptionResponse($transcription, true),
        ]);
    }

    /**
     * Retry failed transcription
     */
    public function retry(Request $request, string $transcriptionId): JsonResponse
    {
        $organizationId = $request->header('X-Organization-Id');
        
        $transcription = VoiceTranscription::where('id', $transcriptionId)
            ->whereHas('message.conversation', function ($query) use ($organizationId) {
                $query->where('organization_id', $organizationId);
            })
            ->with(['message'])
            ->firstOrFail();

        // Check if user has access to this conversation
        if (!$transcription->message->conversation->hasParticipant($request->user()->id)) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        if (!$transcription->canRetry()) {
            return response()->json(['error' => 'Cannot retry this transcription'], 400);
        }

        try {
            $updatedTranscription = $this->transcriptionService->retryTranscription($transcription);

            return response()->json([
                'transcription' => $this->formatTranscriptionResponse($updatedTranscription),
                'message' => 'Transcription retry started',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Retry failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete transcription
     */
    public function destroy(Request $request, string $transcriptionId): JsonResponse
    {
        $organizationId = $request->header('X-Organization-Id');
        
        $transcription = VoiceTranscription::where('id', $transcriptionId)
            ->whereHas('message.conversation', function ($query) use ($organizationId) {
                $query->where('organization_id', $organizationId);
            })
            ->with(['message'])
            ->firstOrFail();

        // Check if user has access to this conversation
        if (!$transcription->message->conversation->hasParticipant($request->user()->id)) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        // Only allow deletion by message sender or conversation admin
        $user = $request->user();
        $canDelete = $transcription->message->sender_id === $user->id ||
                    $transcription->message->conversation->hasAdmin($user->id);

        if (!$canDelete) {
            return response()->json(['error' => 'Insufficient permissions'], 403);
        }

        $this->transcriptionService->deleteTranscription($transcription);

        return response()->json(['message' => 'Transcription deleted successfully']);
    }

    /**
     * Bulk transcribe voice messages
     */
    public function bulkTranscribe(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'message_ids' => 'required|array|min:1|max:50',
            'message_ids.*' => 'string|exists:messages,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $organizationId = $request->header('X-Organization-Id');
        $userId = $request->user()->id;

        // Verify all messages belong to conversations the user can access
        $messages = Message::whereIn('id', $request->message_ids)
            ->whereHas('conversation', function ($query) use ($organizationId, $userId) {
                $query->where('organization_id', $organizationId)
                      ->whereHas('participants', function ($q) use ($userId) {
                          $q->where('user_id', $userId);
                      });
            })
            ->pluck('id')
            ->toArray();

        if (count($messages) !== count($request->message_ids)) {
            return response()->json(['error' => 'Some messages are not accessible'], 403);
        }

        try {
            $results = $this->transcriptionService->bulkTranscribe($messages);

            return response()->json([
                'results' => $results,
                'summary' => [
                    'total' => count($results),
                    'successful' => count(array_filter($results, fn($r) => $r['success'])),
                    'failed' => count(array_filter($results, fn($r) => !$r['success'])),
                ],
                'message' => 'Bulk transcription completed',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Bulk transcription failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get transcription statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $organizationId = $request->header('X-Organization-Id');
        $userId = $request->user()->id;

        $transcriptions = VoiceTranscription::whereHas('message.conversation', function ($query) use ($organizationId, $userId) {
            $query->where('organization_id', $organizationId)
                  ->whereHas('participants', function ($q) use ($userId) {
                      $q->where('user_id', $userId);
                  });
        });

        $stats = [
            'total_transcriptions' => $transcriptions->count(),
            'completed' => $transcriptions->clone()->completed()->count(),
            'failed' => $transcriptions->clone()->failed()->count(),
            'processing' => $transcriptions->clone()->processing()->count(),
            'pending' => $transcriptions->clone()->pending()->count(),
            'languages' => $transcriptions->clone()->completed()
                ->selectRaw('language, COUNT(*) as count')
                ->groupBy('language')
                ->pluck('count', 'language')
                ->toArray(),
            'providers' => $transcriptions->clone()->completed()
                ->selectRaw('provider, COUNT(*) as count')
                ->groupBy('provider')
                ->pluck('count', 'provider')
                ->toArray(),
            'average_confidence' => $transcriptions->clone()->completed()
                ->whereNotNull('confidence')
                ->avg('confidence'),
            'total_duration' => $transcriptions->clone()->completed()
                ->sum('duration'),
            'total_words' => $transcriptions->clone()->completed()
                ->sum('word_count'),
        ];

        return response()->json(['statistics' => $stats]);
    }

    /**
     * Search transcriptions
     */
    public function search(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:2|max:500',
            'language' => 'nullable|string|max:10',
            'min_confidence' => 'nullable|numeric|min:0|max:100',
            'page' => 'integer|min:1',
            'limit' => 'integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $organizationId = $request->header('X-Organization-Id');
        $userId = $request->user()->id;

        $transcriptions = VoiceTranscription::whereHas('message.conversation', function ($query) use ($organizationId, $userId) {
            $query->where('organization_id', $organizationId)
                  ->whereHas('participants', function ($q) use ($userId) {
                      $q->where('user_id', $userId);
                  });
        })
        ->completed()
        ->where('transcript', 'LIKE', '%' . $request->query . '%')
        ->when($request->language, fn($query) => $query->byLanguage($request->language))
        ->when($request->min_confidence, fn($query) => $query->minConfidence($request->min_confidence))
        ->with(['message.conversation', 'attachment'])
        ->orderByDesc('created_at')
        ->paginate($request->input('limit', 20));

        return response()->json([
            'transcriptions' => $transcriptions->items(),
            'pagination' => [
                'current_page' => $transcriptions->currentPage(),
                'last_page' => $transcriptions->lastPage(),
                'per_page' => $transcriptions->perPage(),
                'total' => $transcriptions->total(),
            ],
        ]);
    }

    /**
     * Format transcription response
     */
    private function formatTranscriptionResponse(VoiceTranscription $transcription, bool $includeSegments = false): array
    {
        $data = [
            'id' => $transcription->id,
            'message_id' => $transcription->message_id,
            'status' => $transcription->status,
            'transcript' => $transcription->getTranscript(),
            'language' => $transcription->getLanguage(),
            'confidence' => $transcription->getConfidence(),
            'confidence_percentage' => $transcription->getConfidencePercentage(),
            'duration' => $transcription->getDuration(),
            'duration_formatted' => $transcription->getDurationFormatted(),
            'word_count' => $transcription->getWordCount(),
            'provider' => $transcription->getProvider(),
            'error_message' => $transcription->getErrorMessage(),
            'retry_count' => $transcription->getRetryCount(),
            'can_retry' => $transcription->canRetry(),
            'processed_at' => $transcription->getProcessedAt()?->toISOString(),
            'processing_time' => $transcription->getProcessingTime(),
            'created_at' => $transcription->created_at->toISOString(),
        ];

        if ($includeSegments && $transcription->hasSegments()) {
            $data['segments'] = $transcription->getSegments();
        }

        return $data;
    }
}