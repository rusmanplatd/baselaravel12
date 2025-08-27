<?php

namespace App\Http\Controllers\Api\Chat;

use App\Http\Controllers\Controller;
use App\Models\Chat\Message;
use App\Models\Chat\MessageReadReceipt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageReadReceiptController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function index(Message $message): JsonResponse
    {
        $this->authorize('view', $message->conversation);

        $readReceipts = $message->readReceipts()
            ->with('user:id,name')
            ->get()
            ->map(function ($receipt) {
                return [
                    'user_id' => $receipt->user->id,
                    'user_name' => $receipt->user->name,
                    'read_at' => $receipt->read_at,
                ];
            });

        return response()->json($readReceipts);
    }

    public function store(Request $request, Message $message): JsonResponse
    {
        $this->authorize('view', $message->conversation);

        // Don't allow marking your own messages as read
        if ($message->sender_id === auth()->id()) {
            return response()->json([
                'message' => 'Cannot mark your own message as read',
            ], 400);
        }

        try {
            $readReceipt = MessageReadReceipt::firstOrCreate([
                'message_id' => $message->id,
                'user_id' => auth()->id(),
            ], [
                'read_at' => now(),
            ]);

            $readReceipt->load('user:id,name');

            return response()->json([
                'message' => 'Message marked as read',
                'read_receipt' => [
                    'user_id' => $readReceipt->user->id,
                    'user_name' => $readReceipt->user->name,
                    'read_at' => $readReceipt->read_at,
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to mark message as read',
            ], 500);
        }
    }

    public function markMultipleAsRead(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message_ids' => 'required|array',
            'message_ids.*' => 'required|string|exists:chat_messages,id',
        ]);

        try {
            $messages = Message::whereIn('id', $validated['message_ids'])->get();

            foreach ($messages as $message) {
                $this->authorize('view', $message->conversation);

                // Don't mark own messages as read
                if ($message->sender_id !== auth()->id()) {
                    MessageReadReceipt::firstOrCreate([
                        'message_id' => $message->id,
                        'user_id' => auth()->id(),
                    ], [
                        'read_at' => now(),
                    ]);
                }
            }

            return response()->json([
                'message' => 'Messages marked as read',
                'count' => count($validated['message_ids']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to mark messages as read',
            ], 500);
        }
    }

    public function markConversationAsRead(Request $request, string $conversationId): JsonResponse
    {
        $messages = Message::where('conversation_id', $conversationId)
            ->where('sender_id', '!=', auth()->id())
            ->whereDoesntHave('readReceipts', function ($query) {
                $query->where('user_id', auth()->id());
            })
            ->get();

        foreach ($messages as $message) {
            $this->authorize('view', $message->conversation);
        }

        try {
            foreach ($messages as $message) {
                MessageReadReceipt::firstOrCreate([
                    'message_id' => $message->id,
                    'user_id' => auth()->id(),
                ], [
                    'read_at' => now(),
                ]);
            }

            return response()->json([
                'message' => 'Conversation marked as read',
                'count' => $messages->count(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to mark conversation as read',
            ], 500);
        }
    }
}
