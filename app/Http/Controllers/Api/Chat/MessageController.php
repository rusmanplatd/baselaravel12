<?php

namespace App\Http\Controllers\Api\Chat;

use App\Http\Controllers\Controller;
use App\Models\Chat\Conversation;
use App\Models\Chat\Message;
use App\Models\UserDevice;
use App\Services\SignalProtocolService;
use App\Services\WebhookService;
use App\Events\MessageSent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class MessageController extends Controller
{
    public function __construct(
        private SignalProtocolService $signalService,
        private WebhookService $webhookService
    ) {
        $this->middleware('auth:api');
        $this->middleware('throttle:120,1')->only(['store']);
        $this->middleware('throttle:60,1')->only(['addReaction', 'removeReaction']);
        
        // Apply chat permissions - using standard chat permissions that all users have
        $this->middleware('chat.permission:chat:write,conversationId')->only(['store']);
        $this->middleware('chat.permission:chat:write,conversationId')->only(['update']);
        $this->middleware('chat.permission:chat:write,conversationId')->only(['destroy']);
        $this->middleware('chat.permission:chat:moderate,conversationId')->only(['moderate']);
    }

    /**
     * Get messages for a conversation with pagination
     */
    public function index(Request $request, string $conversationId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'page' => 'integer|min:1',
            'limit' => 'integer|min:1|max:100',
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

        $limit = min($request->input('limit', 50), 100);
        $beforeId = $request->input('before_id');
        $afterId = $request->input('after_id');

        $query = Message::inConversation($conversationId)
            ->with([
                'sender:id,name,avatar',
                'replyTo:id,sender_id,type,created_at',
                'replyTo.sender:id,name',
                'reactions.user:id,name',
                'readReceipts' => function ($query) use ($conversation) {
                    $query->whereIn('user_id', $conversation->participants->pluck('user_id'));
                },
            ])
            ->orderByDesc('created_at');

        // Apply cursor-based pagination
        if ($beforeId) {
            $beforeMessage = Message::findOrFail($beforeId);
            $query->where('created_at', '<', $beforeMessage->created_at);
        }

        if ($afterId) {
            $afterMessage = Message::findOrFail($afterId);
            $query->where('created_at', '>', $afterMessage->created_at)->orderBy('created_at');
        }

        $messages = $query->limit($limit)->get();

        // Decrypt messages for this user/device
        $decryptedMessages = $this->decryptMessagesForUser($messages, $user, $device);

        // Update read status for user
        $this->markMessagesAsRead($conversation, $user, $messages);

        return response()->json([
            'messages' => $decryptedMessages,
            'pagination' => [
                'has_more' => $messages->count() === $limit,
                'oldest_id' => $messages->last()?->id,
                'newest_id' => $messages->first()?->id,
            ],
        ]);
    }

    /**
     * Send a new message
     */
    public function store(Request $request, string $conversationId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:text,image,video,audio,file,voice,poll',
            'content' => 'required|string|max:10000',
            'reply_to_id' => 'nullable|exists:chat_messages,id',
            'scheduled_at' => 'nullable|date|after:now',
            'priority' => 'nullable|in:low,normal,high,urgent',
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

        try {
            DB::beginTransaction();

            // Encrypt message for all participants
            $recipients = $conversation->activeParticipants()->with('user')->get()->pluck('user')->toArray();
            $encryptedMessages = $this->signalService->encryptMessage(
                $request->content,
                $conversation,
                $user,
                $device,
                $recipients
            );

            // Store the message
            $message = Message::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $user->id,
                'reply_to_id' => $request->reply_to_id,
                'type' => $request->type,
                'encrypted_content' => $encryptedMessages[0]['encrypted_content'], // Primary encrypted content
                'content_hash' => $encryptedMessages[0]['content_hash'],
                'metadata' => $request->metadata,
                'scheduled_at' => $request->scheduled_at,
                'message_priority' => $request->input('priority', 'normal'),
                'status' => $request->scheduled_at ? 'scheduled' : 'sent',
            ]);

            // Store delivery receipts for all recipients
            foreach ($encryptedMessages as $encryptedMessage) {
                $message->readReceipts()->create([
                    'user_id' => $encryptedMessage['recipient_user_id'],
                    'device_id' => $encryptedMessage['recipient_device_id'],
                    'delivered_at' => now(),
                ]);
            }

            // Update conversation last activity
            $conversation->update(['last_activity_at' => now()]);

            DB::commit();

            // Load relationships for response
            $message->load([
                'sender:id,name,avatar',
                'replyTo:id,sender_id,type,created_at',
                'reactions.user:id,name',
            ]);

            // Decrypt for response
            $message->decrypted_content = $request->content;

            // Broadcast to other participants (WebSocket/Pusher integration would go here)
            $this->broadcastMessage($message, $conversation);

            // Trigger webhook for message sent
            $this->webhookService->trigger('chat.message.sent', [
                'message' => [
                    'id' => $message->id,
                    'conversation_id' => $conversation->id,
                    'sender_id' => $user->id,
                    'type' => $message->type,
                    'status' => $message->status,
                    'created_at' => $message->created_at->toISOString(),
                    'has_attachments' => !empty($message->metadata['attachments'] ?? []),
                ],
                'conversation' => [
                    'id' => $conversation->id,
                    'name' => $conversation->name,
                    'type' => $conversation->type,
                    'participant_count' => $conversation->getParticipantCount(),
                ],
                'sender' => [
                    'id' => $user->id,
                    'name' => $user->name,
                ],
                'metadata' => [
                    'recipients' => count($encryptedMessages),
                    'is_reply' => !empty($message->reply_to_id),
                ],
            ], $conversation->organization_id);

            Log::info('Message sent', [
                'message_id' => $message->id,
                'conversation_id' => $conversation->id,
                'sender_id' => $user->id,
                'type' => $message->type,
                'recipients' => count($encryptedMessages),
            ]);

            return response()->json([
                'message' => $message,
                'delivery_info' => [
                    'total_recipients' => count($encryptedMessages),
                    'delivered' => count($encryptedMessages),
                ],
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to send message', [
                'conversation_id' => $conversationId,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Failed to send message'], 500);
        }
    }

    /**
     * Get specific message details
     */
    public function show(Request $request, string $conversationId, string $messageId): JsonResponse
    {
        $user = $request->user();
        $device = $this->getCurrentUserDevice($request);
        $conversation = Conversation::findOrFail($conversationId);

        if (! $conversation->hasUser($user->id)) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        $message = Message::with([
            'sender:id,name,avatar',
            'replyTo:id,sender_id,type,created_at',
            'replies' => function ($query) {
                $query->with('sender:id,name,avatar')->orderBy('created_at');
            },
            'reactions.user:id,name',
            'readReceipts.user:id,name',
        ])->findOrFail($messageId);

        // Decrypt message content
        if ($device) {
            try {
                $senderDevice = UserDevice::where('user_id', $message->sender_id)->active()->first();
                $message->decrypted_content = $this->signalService->decryptMessage(
                    $message->encrypted_content,
                    $conversation->encryption_algorithm,
                    $user,
                    $device,
                    $message->sender,
                    $senderDevice
                );
            } catch (\Exception $e) {
                Log::warning('Failed to decrypt message', [
                    'message_id' => $messageId,
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
                $message->decrypted_content = '[Decryption failed]';
            }
        }

        // Mark as read
        $message->markAsRead($user->id);

        return response()->json(['message' => $message]);
    }

    /**
     * Add reaction to message
     */
    public function addReaction(Request $request, string $conversationId, string $messageId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'emoji' => 'required|string|max:10',
            'reaction_type' => 'nullable|in:emoji,custom',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $conversation = Conversation::findOrFail($conversationId);
        $message = Message::findOrFail($messageId);

        if (! $conversation->hasUser($user->id)) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        try {
            $reaction = $message->addReaction($user->id, $request->emoji);

            return response()->json([
                'reaction' => $reaction->load('user:id,name,avatar'),
                'message' => 'Reaction added successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to add reaction'], 500);
        }
    }

    /**
     * Remove reaction from message
     */
    public function removeReaction(Request $request, string $conversationId, string $messageId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'emoji' => 'required|string|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $conversation = Conversation::findOrFail($conversationId);
        $message = Message::findOrFail($messageId);

        if (! $conversation->hasUser($user->id)) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        try {
            $removed = $message->removeReaction($user->id, $request->emoji);

            if ($removed) {
                return response()->json(['message' => 'Reaction removed successfully']);
            } else {
                return response()->json(['error' => 'Reaction not found'], 404);
            }

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to remove reaction'], 500);
        }
    }

    /**
     * Edit a message
     */
    public function update(Request $request, string $conversationId, string $messageId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:10000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $device = $this->getCurrentUserDevice($request);
        $conversation = Conversation::findOrFail($conversationId);
        $message = Message::findOrFail($messageId);

        if (! $device) {
            return response()->json(['error' => 'Device not registered for E2EE'], 400);
        }

        // Check permissions
        if ($message->sender_id !== $user->id) {
            return response()->json(['error' => 'Can only edit your own messages'], 403);
        }

        // Check if message is too old to edit (24 hours)
        if ($message->created_at->diffInHours(now()) > 24) {
            return response()->json(['error' => 'Message is too old to edit'], 403);
        }

        try {
            DB::beginTransaction();

            // Re-encrypt with new content
            $recipients = $conversation->activeParticipants()->with('user')->get()->pluck('user')->toArray();
            $encryptedMessages = $this->signalService->encryptMessage(
                $request->content,
                $conversation,
                $user,
                $device,
                $recipients
            );

            // Update message
            $message->update([
                'encrypted_content' => $encryptedMessages[0]['encrypted_content'],
                'content_hash' => $encryptedMessages[0]['content_hash'],
                'is_edited' => true,
                'edited_at' => now(),
            ]);

            DB::commit();

            $message->decrypted_content = $request->content;

            // Broadcast edit to other participants
            $this->broadcastMessageEdit($message, $conversation);

            return response()->json([
                'message' => $message,
                'success' => 'Message updated successfully',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to edit message', [
                'message_id' => $messageId,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Failed to edit message'], 500);
        }
    }

    /**
     * Delete a message
     */
    public function destroy(Request $request, string $conversationId, string $messageId): JsonResponse
    {
        $user = $request->user();
        $conversation = Conversation::findOrFail($conversationId);
        $message = Message::findOrFail($messageId);

        // Check permissions
        $participant = $conversation->participants()->where('user_id', $user->id)->active()->first();
        $canDelete = ($message->sender_id === $user->id) ||
                    ($participant && $participant->canDeleteMessages());

        if (! $canDelete) {
            return response()->json(['error' => 'Insufficient permissions'], 403);
        }

        try {
            // Soft delete
            $message->update([
                'is_deleted' => true,
                'deleted_at' => now(),
                'encrypted_content' => '', // Clear content
            ]);

            // Broadcast deletion to other participants
            $this->broadcastMessageDeletion($message, $conversation);

            return response()->json(['message' => 'Message deleted successfully']);

        } catch (\Exception $e) {
            Log::error('Failed to delete message', [
                'message_id' => $messageId,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Failed to delete message'], 500);
        }
    }

    /**
     * Upload file attachment
     */
    public function uploadAttachment(Request $request, string $conversationId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:50000', // 50MB max
            'type' => 'required|in:image,video,audio,file',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $conversation = Conversation::findOrFail($conversationId);

        if (! $conversation->hasUser($user->id)) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        try {
            $file = $request->file('file');
            $filename = $file->getClientOriginalName();
            $mimeType = $file->getMimeType();
            $size = $file->getSize();

            // Generate encryption key for file
            $fileKey = random_bytes(32);
            $fileIv = random_bytes(16);

            // Encrypt and store file
            $encryptedContent = $this->encryptFile($file->getContent(), $fileKey, $fileIv);
            $storagePath = 'chat/attachments/'.uniqid().'.encrypted';

            Storage::disk('private')->put($storagePath, $encryptedContent);

            // Generate thumbnail for images/videos
            $thumbnail = null;
            if (str_starts_with($mimeType, 'image/') || str_starts_with($mimeType, 'video/')) {
                $thumbnail = $this->generateEncryptedThumbnail($file, $fileKey);
            }

            return response()->json([
                'file_info' => [
                    'filename' => $filename,
                    'mime_type' => $mimeType,
                    'size' => $size,
                    'encrypted_key' => base64_encode($fileKey),
                    'iv' => base64_encode($fileIv),
                    'storage_path' => encrypt($storagePath),
                    'thumbnail' => $thumbnail,
                ],
                'message' => 'File uploaded successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to upload file attachment', [
                'conversation_id' => $conversationId,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Failed to upload file'], 500);
        }
    }

    /**
     * Decrypt messages for a specific user/device
     */
    private function decryptMessagesForUser($messages, $user, $device): array
    {
        return $messages->map(function ($message) use ($user, $device) {
            if (! $device) {
                $message->decrypted_content = '[Device not registered]';

                return $message;
            }

            try {
                $senderDevice = UserDevice::where('user_id', $message->sender_id)->active()->first();
                $message->decrypted_content = $this->signalService->decryptMessage(
                    $message->encrypted_content,
                    'AES-256-GCM', // This would come from conversation settings
                    $user,
                    $device,
                    $message->sender,
                    $senderDevice
                );
            } catch (\Exception $e) {
                $message->decrypted_content = '[Decryption failed]';
            }

            return $message;
        })->toArray();
    }

    /**
     * Mark messages as read for user
     */
    private function markMessagesAsRead($conversation, $user, $messages): void
    {
        $participant = $conversation->participants()->where('user_id', $user->id)->first();

        if ($participant && $messages->isNotEmpty()) {
            $latestMessage = $messages->first();
            $participant->updateLastRead($latestMessage->id);
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
     * Encrypt file content
     */
    private function encryptFile(string $content, string $key, string $iv): string
    {
        return openssl_encrypt($content, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    }

    /**
     * Generate encrypted thumbnail
     */
    private function generateEncryptedThumbnail($file, string $key): ?string
    {
        // Implementation would depend on image processing library
        // Return base64 encoded encrypted thumbnail
        return null;
    }

    /**
     * Broadcast message to other participants (WebSocket integration point)
     */
    private function broadcastMessage($message, $conversation): void
    {
        // Load sender relationship for broadcasting
        $message->load('sender:id,name,avatar');
        
        // Fire the MessageSent event which will be broadcast via Reverb
        MessageSent::dispatch($message, $conversation);
        
        Log::debug('Broadcasting message', [
            'message_id' => $message->id,
            'conversation_id' => $conversation->id,
        ]);
    }

    /**
     * Broadcast message edit
     */
    private function broadcastMessageEdit($message, $conversation): void
    {
        Log::debug('Broadcasting message edit', [
            'message_id' => $message->id,
            'conversation_id' => $conversation->id,
        ]);
    }

    /**
     * Broadcast message deletion
     */
    private function broadcastMessageDeletion($message, $conversation): void
    {
        Log::debug('Broadcasting message deletion', [
            'message_id' => $message->id,
            'conversation_id' => $conversation->id,
        ]);
    }
}
