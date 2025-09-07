<?php

namespace App\Http\Controllers\Api\Chat;

use App\Http\Controllers\Controller;
use App\Models\Chat\Conversation;
use App\Models\Chat\Message;
use App\Services\WebhookService;
use App\Services\SignalProtocolService;
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
        private WebhookService $webhookService,
        private SignalProtocolService $signalService
    ) {
        $this->middleware('auth:api');
        $this->middleware('throttle:120,1')->only(['store']);
        $this->middleware('throttle:60,1')->only(['addReaction', 'removeReaction']);

        // Apply chat permissions - using standard chat permissions that all users have
        $this->middleware('chat.permission:chat:write,conversation')->only(['store']);
        $this->middleware('chat.permission:chat:write,conversation')->only(['update']);
        $this->middleware('chat.permission:chat:write,conversation')->only(['destroy']);
        $this->middleware('chat.permission:chat:moderate,conversation')->only(['moderate']);
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
                'replyTo:id,sender_id,message_type,created_at',
                'replyTo.sender:id,name',
                'reactions.user:id,name',
                'readReceipts' => function ($query) use ($conversation) {
                    $query->whereIn('recipient_user_id', $conversation->participants->pluck('user_id'));
                },
            ])
            ->orderBy('created_at');

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

        // Messages are now decrypted on the client side
        $decryptedMessages = $messages->map(function ($message) {
            $message->type = $message->message_type;
            return $message;
        })->toArray();

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
            'content' => 'nullable|string|max:10000', // Optional for E2EE (client encrypts)
            'encrypted_content' => 'nullable|string', // Required for E2EE
            'content_hash' => 'nullable|string', // Required for E2EE
            'encryption_algorithm' => 'nullable|string', // Required for E2EE
            'reply_to_id' => 'nullable|exists:chat_messages,id',
            'scheduled_at' => 'nullable|date|after:now',
            'priority' => 'nullable|in:low,normal,high,urgent',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $conversation = Conversation::findOrFail($conversationId);

        // Check if user can send messages
        $participant = $conversation->participants()->where('user_id', $user->id)->active()->first();
        if (! $participant || ! $participant->canSendMessages()) {
            return response()->json(['error' => 'Cannot send messages to this conversation'], 403);
        }

        try {
            DB::beginTransaction();

            // In proper E2EE, the message should already be encrypted on the client side
            // The server should only store the encrypted content
            $encryptedContent = $request->input('encrypted_content');
            $contentHash = $request->input('content_hash');

            if (!$encryptedContent) {
                // In proper E2EE, encrypted content should always be provided by the client
                return response()->json(['error' => 'Encrypted content is required for E2EE messages'], 400);
            }

            // Store the message
            $message = Message::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $user->id,
                'sender_device_id' => null, // Not needed for E2EE
                'reply_to_id' => $request->reply_to_id,
                'message_type' => $request->type,
                'encrypted_content' => $encryptedContent,
                'content_hash' => $contentHash,
                'encrypted_metadata' => $request->metadata ? json_encode($request->metadata) : null,
            ]);

            // Create delivery receipts for all conversation participants
            $participants = $conversation->activeParticipants()->with('user')->get();
            foreach ($participants as $participant) {
                if ($participant->user_id !== $user->id) { // Don't create receipt for sender
                    $message->readReceipts()->create([
                        'recipient_user_id' => $participant->user_id,
                        'recipient_device_id' => null, // Will be set when message is delivered to specific device
                        'status' => 'sent',
                        'delivered_at' => now(),
                    ]);
                }
            }

            // Update conversation last activity
            $conversation->update(['last_activity_at' => now()]);

            DB::commit();

            // Load relationships for response
            $message->load([
                'sender:id,name,avatar',
                'replyTo:id,sender_id,message_type,created_at',
                'reactions.user:id,name',
            ]);

            // In proper E2EE, the server should not decrypt messages
            // The client will decrypt the message using the encrypted_content
            $message->decrypted_content = null; // Don't include decrypted content in response
            // Map message_type to type for frontend compatibility
            $message->type = $message->message_type;

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
                    'is_reply' => !empty($message->reply_to_id),
                ],
            ], $conversation->organization_id);

            Log::info('Message sent', [
                'message_id' => $message->id,
                'conversation_id' => $conversation->id,
                'sender_id' => $user->id,
                'type' => $message->type,
            ]);

            return response()->json([
                'message' => $message,
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

        // In proper E2EE, the server should not decrypt messages
        // The client will decrypt the message using the encrypted_content
        $message->decrypted_content = null;

        // Mark as read
        $message->markAsRead($user->id);

        // Map message_type to type for frontend compatibility
        $message->type = $message->message_type;

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
            'content' => 'nullable|string|max:10000', // Optional for E2EE
            'encrypted_content' => 'nullable|string', // Required for E2EE
            'content_hash' => 'nullable|string', // Required for E2EE
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $conversation = Conversation::findOrFail($conversationId);
        $message = Message::findOrFail($messageId);

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

            // In proper E2EE, the client should provide the encrypted content
            $encryptedContent = $request->input('encrypted_content');
            $contentHash = $request->input('content_hash');

            if (!$encryptedContent) {
                return response()->json(['error' => 'Encrypted content is required for E2EE message editing'], 400);
            }

            // Update message
            $message->update([
                'encrypted_content' => $encryptedContent,
                'content_hash' => $contentHash,
                'is_edited' => true,
                'edited_at' => now(),
            ]);

            DB::commit();

            // Don't include decrypted content in response
            $message->decrypted_content = null;

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

    /**
     * Migration endpoint to decrypt existing quantum-encrypted messages
     * This is a temporary endpoint to help migrate from old quantum system to new client-side E2EE
     */
    public function migrateMessage(Request $request, string $messageId): JsonResponse
    {
        $user = $request->user();
        $message = Message::findOrFail($messageId);

        // Check if user has access to this message
        if (!$message->conversation->hasUser($user->id)) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        try {
            // Try to decrypt the message using the old server-side logic
            $data = json_decode($message->encrypted_content, true);

            if (isset($data['ciphertext']) && isset($data['encrypted_message']) && isset($data['nonce'])) {
                // This is a quantum-encrypted message

                // Check if the encrypted_message is actually base64-encoded plain text (fallback)
                $decoded = base64_decode($data['encrypted_message'], true);
                if ($decoded !== false && ctype_print($decoded)) {
                    // This is a fallback message with base64-encoded plain text
                    return response()->json([
                        'message_id' => $message->id,
                        'decrypted_content' => $decoded,
                        'migration_status' => 'success',
                        'message_type' => 'fallback_decoded'
                    ]);
                } else {
                    // This is real quantum encryption - cannot decrypt without quantum keys
                    return response()->json([
                        'message_id' => $message->id,
                        'decrypted_content' => '[Message encrypted with quantum cryptography - cannot be decrypted in current environment]',
                        'migration_status' => 'quantum_encrypted',
                        'message_type' => 'quantum_encrypted',
                        'error' => 'Message is quantum-encrypted and cannot be decrypted without quantum keys'
                    ]);
                }
            } else {
                // Unknown format
                return response()->json([
                    'message_id' => $message->id,
                    'decrypted_content' => null,
                    'migration_status' => 'unknown_format',
                    'message_type' => 'unknown',
                    'error' => 'Unknown message format'
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Message migration failed', [
                'message_id' => $messageId,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message_id' => $message->id,
                'decrypted_content' => null,
                'migration_status' => 'error',
                'error' => 'Migration failed: ' . $e->getMessage()
            ], 500);
        }
    }

}
