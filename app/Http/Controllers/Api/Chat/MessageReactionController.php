<?php

namespace App\Http\Controllers\Api\Chat;

use App\Http\Controllers\Controller;
use App\Models\Chat\Message;
use App\Models\Chat\MessageReaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageReactionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function index(Message $message): JsonResponse
    {
        $this->authorize('view', $message->conversation);

        $reactions = $message->reactions()
            ->with('user:id,name')
            ->get()
            ->groupBy('emoji')
            ->map(function ($reactions) {
                return [
                    'emoji' => $reactions->first()->emoji,
                    'count' => $reactions->count(),
                    'users' => $reactions->map(function ($reaction) {
                        return [
                            'id' => $reaction->user->id,
                            'name' => $reaction->user->name,
                            'reacted_at' => $reaction->created_at,
                        ];
                    })->toArray(),
                ];
            })
            ->values();

        return response()->json($reactions);
    }

    public function store(Request $request, Message $message): JsonResponse
    {
        $this->authorize('view', $message->conversation);

        $validated = $request->validate([
            'emoji' => 'required|string|max:10',
        ]);

        try {
            $reaction = MessageReaction::firstOrCreate([
                'message_id' => $message->id,
                'user_id' => auth()->id(),
                'emoji' => $validated['emoji'],
            ]);

            $reaction->load('user:id,name');

            return response()->json([
                'message' => 'Reaction added successfully',
                'reaction' => [
                    'id' => $reaction->id,
                    'emoji' => $reaction->emoji,
                    'user' => $reaction->user,
                    'created_at' => $reaction->created_at,
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to add reaction',
            ], 500);
        }
    }

    public function destroy(Message $message, string $emoji): JsonResponse
    {
        $this->authorize('view', $message->conversation);

        $reaction = MessageReaction::where([
            'message_id' => $message->id,
            'user_id' => auth()->id(),
            'emoji' => $emoji,
        ])->first();

        if (! $reaction) {
            return response()->json([
                'error' => 'Reaction not found',
            ], 404);
        }

        try {
            $reaction->delete();

            return response()->json([
                'message' => 'Reaction removed successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to remove reaction',
            ], 500);
        }
    }

    public function toggle(Request $request, Message $message): JsonResponse
    {
        $this->authorize('view', $message->conversation);

        $validated = $request->validate([
            'emoji' => 'required|string|max:10',
        ]);

        try {
            $reaction = MessageReaction::where([
                'message_id' => $message->id,
                'user_id' => auth()->id(),
                'emoji' => $validated['emoji'],
            ])->first();

            if ($reaction) {
                $reaction->delete();

                return response()->json([
                    'message' => 'Reaction removed',
                    'action' => 'removed',
                ]);
            } else {
                $reaction = MessageReaction::create([
                    'message_id' => $message->id,
                    'user_id' => auth()->id(),
                    'emoji' => $validated['emoji'],
                ]);

                $reaction->load('user:id,name');

                return response()->json([
                    'message' => 'Reaction added',
                    'action' => 'added',
                    'reaction' => [
                        'id' => $reaction->id,
                        'emoji' => $reaction->emoji,
                        'user' => $reaction->user,
                        'created_at' => $reaction->created_at,
                    ],
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to toggle reaction',
            ], 500);
        }
    }
}
