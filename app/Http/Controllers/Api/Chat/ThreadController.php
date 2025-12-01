<?php

namespace App\Http\Controllers\Api\Chat;

use App\Http\Controllers\Controller;
use App\Models\Chat\Conversation;
use App\Models\Chat\Message;
use App\Models\UserDevice;
use App\Services\GroupEncryptionService;
use App\Services\SignalProtocolService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ThreadController extends Controller
{
    public function __construct(
        private SignalProtocolService $signalService,
        private GroupEncryptionService $groupEncryptionService
    ) {
        $this->middleware('auth:api');
        $this->middleware('throttle:120,1')->only(['reply']);
    }

    /**
     * Get thread messages for a parent message
     */
    public function index(Request $request, string $conversationId, string $messageId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'nullable|integer|min:1|max:100',
            'before_id' => 'nullable|exists:chat_messages,id',
            'after_id' => 'nullable|exists:chat_messages,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $device = $this->getCurrentUserDevice($request);
        $conversation = Conversation::findOrFail($conversationId);

        // Check if user is participant
        if (! $conversation->hasUser($user->id)) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        // Get parent message
        $parentMessage = Message::where('conversation_id', $conversationId)
            ->where('id', $messageId)
            ->with(['sender:id,name,avatar', 'reactions.user:id,name'])
            ->firstOrFail();

        $limit = min($request->input('limit', 50), 100);
        $beforeId = $request->input('before_id');
        $afterId = $request->input('after_id');

        // Get thread messages
        $query = Message::where('conversation_id', $conversationId)
            ->where('thread_id', $messageId)
            ->with([
                'sender:id,name,avatar',
                'replyTo:id,sender_id,type,created_at',
                'replyTo.sender:id,name',
                'reactions.user:id,name',
                'readReceipts' => function ($q) use ($conversation) {
                    $q->whereIn('user_id', $conversation->participants->pluck('user_id'));
                },
            ])
            ->orderByDesc('created_at');

        // Apply cursor pagination
        if ($beforeId) {
            $beforeMessage = Message::findOrFail($beforeId);
            $query->where('created_at', '<', $beforeMessage->created_at);
        }

        if ($afterId) {
            $afterMessage = Message::findOrFail($afterId);
            $query->where('created_at', '>', $afterMessage->created_at)->orderBy('created_at');
        }

        $threadMessages = $query->limit($limit)->get();

        // Decrypt messages for this user/device
        $decryptedMessages = $this->decryptMessages($threadMessages, $conversation, $user, $device);

        // Get thread statistics
        $threadStats = [
            'total_replies' => Message::where('thread_id', $messageId)->count(),
            'participant_count' => Message::where('thread_id', $messageId)
                ->distinct('sender_id')
                ->count(),
            'last_reply_at' => Message::where('thread_id', $messageId)
                ->latest('created_at')
                ->value('created_at'),
        ];

        return response()->json([
            'parent_message' => $this->decryptMessage($parentMessage, $conversation, $user, $device),
            'thread_messages' => $decryptedMessages,
            'thread_stats' => $threadStats,
            'pagination' => [
                'has_more' => $threadMessages->count() === $limit,
                'oldest_id' => $threadMessages->last()?->id,
                'newest_id' => $threadMessages->first()?->id,
            ],
        ]);
    }

    /**
     * Reply to a message (create thread message)
     */
    public function reply(Request $request, string $conversationId, string $messageId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:10000',
            'type' => 'nullable|in:text,image,video,audio,file,voice',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $device = $this->getCurrentUserDevice($request);
        $conversation = Conversation::findOrFail($conversationId);

        if (! $device) {
            return response()->json(['error' => 'Device not registered for E2EE'], 400);
        }

        // Check if user can send messages
        $participant = $conversation->participants()->where('user_id', $user->id)->active()->first();
        if (! $participant || ! $participant->canSendMessages()) {
            return response()->json(['error' => 'Cannot send messages to this conversation'], 403);
        }

        // Verify parent message exists
        $parentMessage = Message::where('conversation_id', $conversationId)
            ->where('id', $messageId)
            ->firstOrFail();

        try {
            DB::beginTransaction();

            // Encrypt thread reply
            $encryptionResult = $this->encryptMessage(
                $request->content,
                $conversation,
                $user,
                $device
            );

            // Create thread message
            $threadMessage = Message::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $user->id,
                'thread_id' => $messageId, // This makes it a thread reply
                'reply_to_id' => $messageId, // Reference to parent message
                'type' => $request->input('type', 'text'),
                'encrypted_content' => $encryptionResult['encrypted_content'],
                'content_hash' => $encryptionResult['content_hash'],
                'metadata' => $request->metadata,
                'status' => 'sent',
            ]);

            // Create delivery receipts for thread subscribers
            $this->createThreadDeliveryReceipts($threadMessage, $parentMessage);

            // Update conversation last activity
            $conversation->update(['last_activity_at' => now()]);

            DB::commit();

            // Load relationships for response
            $threadMessage->load([
                'sender:id,name,avatar',
                'replyTo:id,sender_id,type,created_at',
                'reactions.user:id,name',
            ]);

            // Decrypt for response
            $threadMessage->decrypted_content = $request->content;

            // Broadcast to thread subscribers
            $this->broadcastThreadReply($threadMessage, $parentMessage);

            Log::info('Thread reply sent', [
                'thread_message_id' => $threadMessage->id,
                'parent_message_id' => $messageId,
                'conversation_id' => $conversation->id,
                'sender_id' => $user->id,
            ]);

            return response()->json([
                'message' => $threadMessage,
                'thread_stats' => [
                    'total_replies' => Message::where('thread_id', $messageId)->count(),
                ],
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to send thread reply', [
                'parent_message_id' => $messageId,
                'conversation_id' => $conversationId,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Failed to send thread reply'], 500);
        }
    }

    /**
     * Get thread summary for a message
     */
    public function summary(Request $request, string $conversationId, string $messageId): JsonResponse
    {
        $user = $request->user();
        $conversation = Conversation::findOrFail($conversationId);

        if (! $conversation->hasUser($user->id)) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        $parentMessage = Message::where('conversation_id', $conversationId)
            ->where('id', $messageId)
            ->firstOrFail();

        // Get thread statistics
        $threadStats = DB::table('chat_messages')
            ->where('thread_id', $messageId)
            ->selectRaw('
                COUNT(*) as total_replies,
                COUNT(DISTINCT sender_id) as unique_participants,
                MAX(created_at) as last_reply_at
            ')
            ->first();

        // Get recent participants in thread
        $recentParticipants = Message::where('thread_id', $messageId)
            ->with('sender:id,name,avatar')
            ->distinct('sender_id')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->pluck('sender')
            ->unique('id')
            ->values();

        // Get latest thread messages preview
        $latestReplies = Message::where('thread_id', $messageId)
            ->with('sender:id,name,avatar')
            ->orderByDesc('created_at')
            ->limit(3)
            ->get();

        // Check if user has unread thread messages
        $participant = $conversation->participants()->where('user_id', $user->id)->first();
        $unreadCount = 0;

        if ($participant && $participant->last_read_message_id) {
            $lastReadMessage = Message::find($participant->last_read_message_id);
            if ($lastReadMessage) {
                $unreadCount = Message::where('thread_id', $messageId)
                    ->where('created_at', '>', $lastReadMessage->created_at)
                    ->where('sender_id', '!=', $user->id)
                    ->count();
            }
        } else {
            $unreadCount = Message::where('thread_id', $messageId)
                ->where('sender_id', '!=', $user->id)
                ->count();
        }

        return response()->json([
            'thread_stats' => [
                'total_replies' => (int) $threadStats->total_replies,
                'unique_participants' => (int) $threadStats->unique_participants,
                'last_reply_at' => $threadStats->last_reply_at,
                'unread_count' => $unreadCount,
            ],
            'recent_participants' => $recentParticipants,
            'latest_replies_preview' => $latestReplies->map(function ($message) {
                return [
                    'id' => $message->id,
                    'sender' => $message->sender,
                    'type' => $message->type,
                    'created_at' => $message->created_at,
                ];
            }),
        ]);
    }

    /**
     * Subscribe to thread notifications
     */
    public function subscribe(Request $request, string $conversationId, string $messageId): JsonResponse
    {
        $user = $request->user();
        $conversation = Conversation::findOrFail($conversationId);

        if (! $conversation->hasUser($user->id)) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        $parentMessage = Message::where('conversation_id', $conversationId)
            ->where('id', $messageId)
            ->firstOrFail();

        try {
            // Store thread subscription (you might want a dedicated table for this)
            $subscriptionKey = "thread_subscription_{$messageId}_{$user->id}";
            cache()->put($subscriptionKey, true, now()->addDays(30));

            Log::info('User subscribed to thread', [
                'message_id' => $messageId,
                'user_id' => $user->id,
            ]);

            return response()->json(['message' => 'Subscribed to thread notifications']);

        } catch (\Exception $e) {
            Log::error('Failed to subscribe to thread', [
                'message_id' => $messageId,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Failed to subscribe to thread'], 500);
        }
    }

    /**
     * Unsubscribe from thread notifications
     */
    public function unsubscribe(Request $request, string $conversationId, string $messageId): JsonResponse
    {
        $user = $request->user();

        $subscriptionKey = "thread_subscription_{$messageId}_{$user->id}";
        cache()->forget($subscriptionKey);

        return response()->json(['message' => 'Unsubscribed from thread notifications']);
    }

    /**
     * Mark thread as read
     */
    public function markAsRead(Request $request, string $conversationId, string $messageId): JsonResponse
    {
        $user = $request->user();
        $conversation = Conversation::findOrFail($conversationId);

        if (! $conversation->hasUser($user->id)) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        try {
            // Get the latest message in the thread
            $latestThreadMessage = Message::where('thread_id', $messageId)
                ->orderByDesc('created_at')
                ->first();

            if ($latestThreadMessage) {
                // Update participant's last read message to the latest in thread
                $participant = $conversation->participants()->where('user_id', $user->id)->first();
                if ($participant) {
                    $participant->updateLastRead($latestThreadMessage->id);
                }

                // Mark all thread messages as read
                Message::where('thread_id', $messageId)
                    ->whereDoesntHave('readReceipts', function ($query) use ($user) {
                        $query->where('user_id', $user->id);
                    })
                    ->get()
                    ->each(function ($message) use ($user) {
                        $message->markAsRead($user->id);
                    });
            }

            return response()->json(['message' => 'Thread marked as read']);

        } catch (\Exception $e) {
            Log::error('Failed to mark thread as read', [
                'message_id' => $messageId,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Failed to mark thread as read'], 500);
        }
    }

    /**
     * Get current user device from request
     */
    private function getCurrentUserDevice(Request $request): ?UserDevice
    {
        $deviceFingerprint = $request->header('X-Device-Fingerprint');

        if (! $deviceFingerprint) {
            return null;
        }

        return UserDevice::where('user_id', $request->user()->id)
            ->where('device_fingerprint', $deviceFingerprint)
            ->active()
            ->first();
    }

    /**
     * Encrypt message based on conversation type
     */
    private function encryptMessage(
        string $content,
        Conversation $conversation,
        $user,
        UserDevice $device
    ): array {
        if ($conversation->isGroup()) {
            return $this->groupEncryptionService->encryptGroupMessage(
                $content,
                $conversation,
                $user,
                $device
            );
        } else {
            // For direct messages, use Signal Protocol
            $recipients = $conversation->activeParticipants()
                ->with('user')
                ->get()
                ->pluck('user')
                ->filter(fn ($u) => $u->id !== $user->id)
                ->toArray();

            $encryptedMessages = $this->signalService->encryptMessage(
                $content,
                $conversation,
                $user,
                $device,
                $recipients
            );

            return [
                'encrypted_content' => $encryptedMessages[0]['encrypted_content'],
                'content_hash' => $encryptedMessages[0]['content_hash'],
            ];
        }
    }

    /**
     * Decrypt messages for user
     */
    private function decryptMessages($messages, Conversation $conversation, $user, ?UserDevice $device): array
    {
        if (! $device) {
            return $messages->map(function ($message) {
                $message->decrypted_content = '[Device not registered]';

                return $message;
            })->toArray();
        }

        return $messages->map(function ($message) use ($conversation, $user, $device) {
            return $this->decryptMessage($message, $conversation, $user, $device);
        })->toArray();
    }

    /**
     * Decrypt single message for user
     */
    private function decryptMessage($message, Conversation $conversation, $user, UserDevice $device)
    {
        try {
            if ($conversation->isGroup()) {
                $message->decrypted_content = $this->groupEncryptionService->decryptGroupMessage(
                    $message->encrypted_content,
                    $conversation,
                    $user,
                    $device
                );
            } else {
                $senderDevice = UserDevice::where('user_id', $message->sender_id)->active()->first();
                $message->decrypted_content = $this->signalService->decryptMessage(
                    $message->encrypted_content,
                    $conversation->encryption_algorithm,
                    $user,
                    $device,
                    $message->sender,
                    $senderDevice
                );
            }
        } catch (\Exception $e) {
            Log::warning('Failed to decrypt message', [
                'message_id' => $message->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            $message->decrypted_content = '[Decryption failed]';
        }

        return $message;
    }

    /**
     * Create delivery receipts for thread subscribers
     */
    private function createThreadDeliveryReceipts(Message $threadMessage, Message $parentMessage): void
    {
        // Get all participants who have interacted with the thread
        $threadParticipants = Message::where('thread_id', $parentMessage->id)
            ->distinct('sender_id')
            ->pluck('sender_id');

        // Add parent message sender
        $threadParticipants->push($parentMessage->sender_id);
        $threadParticipants = $threadParticipants->unique();

        // Get their active devices
        $devices = UserDevice::whereIn('user_id', $threadParticipants)
            ->active()
            ->get();

        foreach ($devices as $device) {
            $threadMessage->readReceipts()->create([
                'user_id' => $device->user_id,
                'device_id' => $device->id,
                'delivered_at' => now(),
            ]);
        }
    }

    /**
     * Broadcast thread reply to subscribers
     */
    private function broadcastThreadReply(Message $threadMessage, Message $parentMessage): void
    {
        // This would integrate with your real-time system (Pusher, WebSockets, etc.)
        Log::debug('Broadcasting thread reply', [
            'thread_message_id' => $threadMessage->id,
            'parent_message_id' => $parentMessage->id,
        ]);

        // Example integration point for real-time notifications
        // broadcast(new ThreadReplyEvent($threadMessage, $parentMessage));
    }
}
