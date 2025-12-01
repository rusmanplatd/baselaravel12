<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ScheduledMessage;
use App\Models\Chat\Conversation;
use App\Services\MessageSchedulingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ScheduledMessageController extends Controller
{
    public function __construct(
        private MessageSchedulingService $schedulingService
    ) {
        $this->middleware('auth:api');
        $this->middleware('throttle:60,1');
    }

    /**
     * List scheduled messages
     */
    public function index(Request $request): JsonResponse
    {
        $organizationId = $request->header('X-Organization-Id');
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'conversation_id' => 'nullable|string|exists:conversations,id',
            'status' => 'nullable|array',
            'status.*' => 'string|in:scheduled,sending,sent,failed,cancelled',
            'page' => 'integer|min:1',
            'limit' => 'integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $query = ScheduledMessage::forSender($user->id)
            ->with(['conversation'])
            ->whereHas('conversation', function ($q) use ($organizationId) {
                $q->where('organization_id', $organizationId);
            });

        if ($request->conversation_id) {
            $query->forConversation($request->conversation_id);
        }

        if ($request->status) {
            $query->whereIn('status', $request->status);
        }

        $messages = $query->orderByDesc('created_at')
            ->paginate($request->input('limit', 20));

        return response()->json([
            'scheduled_messages' => $messages->items(),
            'pagination' => [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
            ],
        ]);
    }

    /**
     * Create scheduled message
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'conversation_id' => 'required|string|exists:conversations,id',
            'content' => 'required|string|max:10000',
            'content_type' => 'string|in:text,markdown,html',
            'scheduled_for' => 'required|date|after:now',
            'timezone' => 'string|max:50',
            'metadata' => 'array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $organizationId = $request->header('X-Organization-Id');
        $user = $request->user();

        $conversation = Conversation::where('id', $request->conversation_id)
            ->where('organization_id', $organizationId)
            ->firstOrFail();

        // Check if user can send messages to this conversation
        if (!$conversation->hasParticipant($user->id)) {
            return response()->json(['error' => 'You are not a participant in this conversation'], 403);
        }

        try {
            $scheduledFor = Carbon::parse($request->scheduled_for);
            
            // Apply timezone if provided
            if ($request->timezone) {
                $scheduledFor->setTimezone('UTC');
            }

            $scheduledMessage = $this->schedulingService->scheduleMessage(
                $conversation,
                $user,
                $request->content,
                $scheduledFor,
                $request->input('content_type', 'text'),
                $request->input('timezone', 'UTC'),
                $request->input('metadata', [])
            );

            return response()->json([
                'scheduled_message' => $this->formatScheduledMessage($scheduledMessage),
                'message' => 'Message scheduled successfully',
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to schedule message: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * Show scheduled message
     */
    public function show(Request $request, string $scheduledMessageId): JsonResponse
    {
        $organizationId = $request->header('X-Organization-Id');
        $user = $request->user();

        $scheduledMessage = ScheduledMessage::where('id', $scheduledMessageId)
            ->where('sender_id', $user->id)
            ->whereHas('conversation', function ($query) use ($organizationId) {
                $query->where('organization_id', $organizationId);
            })
            ->with(['conversation', 'sentMessage'])
            ->firstOrFail();

        return response()->json([
            'scheduled_message' => $this->formatScheduledMessage($scheduledMessage, true)
        ]);
    }

    /**
     * Update scheduled message
     */
    public function update(Request $request, string $scheduledMessageId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'content' => 'string|max:10000',
            'content_type' => 'string|in:text,markdown,html',
            'scheduled_for' => 'date|after:now',
            'timezone' => 'string|max:50',
            'metadata' => 'array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $organizationId = $request->header('X-Organization-Id');
        $user = $request->user();

        $scheduledMessage = ScheduledMessage::where('id', $scheduledMessageId)
            ->where('sender_id', $user->id)
            ->whereHas('conversation', function ($query) use ($organizationId) {
                $query->where('organization_id', $organizationId);
            })
            ->firstOrFail();

        if (!$scheduledMessage->canCancel()) {
            return response()->json(['error' => 'Cannot modify this scheduled message'], 400);
        }

        try {
            $updateData = $request->only(['content', 'content_type', 'metadata']);

            if ($request->scheduled_for) {
                $scheduledFor = Carbon::parse($request->scheduled_for);
                if ($request->timezone) {
                    $scheduledFor->setTimezone('UTC');
                }
                $updateData['scheduled_for'] = $scheduledFor;
            }

            if ($request->timezone) {
                $updateData['timezone'] = $request->timezone;
            }

            $scheduledMessage->update($updateData);

            return response()->json([
                'scheduled_message' => $this->formatScheduledMessage($scheduledMessage),
                'message' => 'Scheduled message updated successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update scheduled message: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * Cancel scheduled message
     */
    public function cancel(Request $request, string $scheduledMessageId): JsonResponse
    {
        $organizationId = $request->header('X-Organization-Id');
        $user = $request->user();

        $scheduledMessage = ScheduledMessage::where('id', $scheduledMessageId)
            ->where('sender_id', $user->id)
            ->whereHas('conversation', function ($query) use ($organizationId) {
                $query->where('organization_id', $organizationId);
            })
            ->firstOrFail();

        try {
            $this->schedulingService->cancelScheduledMessage($scheduledMessage);

            return response()->json([
                'scheduled_message' => $this->formatScheduledMessage($scheduledMessage),
                'message' => 'Scheduled message cancelled successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to cancel scheduled message: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * Retry failed scheduled message
     */
    public function retry(Request $request, string $scheduledMessageId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'scheduled_for' => 'nullable|date|after:now',
            'timezone' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $organizationId = $request->header('X-Organization-Id');
        $user = $request->user();

        $scheduledMessage = ScheduledMessage::where('id', $scheduledMessageId)
            ->where('sender_id', $user->id)
            ->whereHas('conversation', function ($query) use ($organizationId) {
                $query->where('organization_id', $organizationId);
            })
            ->firstOrFail();

        try {
            $newScheduledTime = null;
            if ($request->scheduled_for) {
                $newScheduledTime = Carbon::parse($request->scheduled_for);
                if ($request->timezone) {
                    $newScheduledTime->setTimezone('UTC');
                }
            }

            $this->schedulingService->retryScheduledMessage($scheduledMessage, $newScheduledTime);

            return response()->json([
                'scheduled_message' => $this->formatScheduledMessage($scheduledMessage),
                'message' => 'Scheduled message retry queued successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to retry scheduled message: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * Delete scheduled message
     */
    public function destroy(Request $request, string $scheduledMessageId): JsonResponse
    {
        $organizationId = $request->header('X-Organization-Id');
        $user = $request->user();

        $scheduledMessage = ScheduledMessage::where('id', $scheduledMessageId)
            ->where('sender_id', $user->id)
            ->whereHas('conversation', function ($query) use ($organizationId) {
                $query->where('organization_id', $organizationId);
            })
            ->firstOrFail();

        // Only allow deletion of sent, cancelled, or failed messages
        if (!in_array($scheduledMessage->status, ['sent', 'cancelled', 'failed'])) {
            return response()->json(['error' => 'Cannot delete active scheduled messages'], 400);
        }

        $scheduledMessage->delete();

        return response()->json(['message' => 'Scheduled message deleted successfully']);
    }

    /**
     * Get scheduling statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $organizationId = $request->header('X-Organization-Id');
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'conversation_id' => 'nullable|string|exists:conversations,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $conversationId = null;
        if ($request->conversation_id) {
            // Verify conversation belongs to organization and user has access
            $conversation = Conversation::where('id', $request->conversation_id)
                ->where('organization_id', $organizationId)
                ->whereHas('participants', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->first();

            if (!$conversation) {
                return response()->json(['error' => 'Conversation not found or access denied'], 404);
            }

            $conversationId = $conversation->id;
        }

        $statistics = $this->schedulingService->getSchedulingStatistics($user, $conversationId);

        return response()->json(['statistics' => $statistics]);
    }

    /**
     * Bulk operations on scheduled messages
     */
    public function bulkAction(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'action' => 'required|string|in:cancel,retry,delete',
            'message_ids' => 'required|array|min:1|max:50',
            'message_ids.*' => 'string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $organizationId = $request->header('X-Organization-Id');
        $user = $request->user();

        $scheduledMessages = ScheduledMessage::whereIn('id', $request->message_ids)
            ->where('sender_id', $user->id)
            ->whereHas('conversation', function ($query) use ($organizationId) {
                $query->where('organization_id', $organizationId);
            })
            ->get();

        if ($scheduledMessages->count() !== count($request->message_ids)) {
            return response()->json(['error' => 'Some messages were not found or access denied'], 403);
        }

        $results = [];
        $successful = 0;
        $failed = 0;

        foreach ($scheduledMessages as $message) {
            try {
                switch ($request->action) {
                    case 'cancel':
                        $this->schedulingService->cancelScheduledMessage($message);
                        break;
                    case 'retry':
                        $this->schedulingService->retryScheduledMessage($message);
                        break;
                    case 'delete':
                        if (in_array($message->status, ['sent', 'cancelled', 'failed'])) {
                            $message->delete();
                        } else {
                            throw new \Exception('Cannot delete active scheduled message');
                        }
                        break;
                }

                $results[$message->id] = ['success' => true];
                $successful++;

            } catch (\Exception $e) {
                $results[$message->id] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
                $failed++;
            }
        }

        return response()->json([
            'results' => $results,
            'summary' => [
                'total' => count($request->message_ids),
                'successful' => $successful,
                'failed' => $failed,
            ],
            'message' => "Bulk {$request->action} completed",
        ]);
    }

    /**
     * Format scheduled message for API response
     */
    private function formatScheduledMessage(ScheduledMessage $scheduledMessage, bool $includeDetails = false): array
    {
        $data = [
            'id' => $scheduledMessage->id,
            'conversation_id' => $scheduledMessage->conversation_id,
            'content' => $scheduledMessage->getContent(),
            'content_type' => $scheduledMessage->getContentType(),
            'scheduled_for' => $scheduledMessage->scheduled_for->toISOString(),
            'timezone' => $scheduledMessage->timezone,
            'status' => $scheduledMessage->status,
            'retry_count' => $scheduledMessage->retry_count,
            'max_retries' => $scheduledMessage->max_retries,
            'error_message' => $scheduledMessage->error_message,
            'can_retry' => $scheduledMessage->canRetry(),
            'can_cancel' => $scheduledMessage->canCancel(),
            'time_until_send' => $scheduledMessage->getTimeUntilSend(),
            'created_at' => $scheduledMessage->created_at->toISOString(),
            'updated_at' => $scheduledMessage->updated_at->toISOString(),
        ];

        if ($scheduledMessage->sent_at) {
            $data['sent_at'] = $scheduledMessage->sent_at->toISOString();
        }

        if ($scheduledMessage->cancelled_at) {
            $data['cancelled_at'] = $scheduledMessage->cancelled_at->toISOString();
        }

        if ($includeDetails) {
            $data['metadata'] = $scheduledMessage->getMetadata();
            
            if ($scheduledMessage->sentMessage) {
                $data['sent_message'] = [
                    'id' => $scheduledMessage->sentMessage->id,
                    'created_at' => $scheduledMessage->sentMessage->created_at->toISOString(),
                ];
            }
        }

        return $data;
    }
}