<?php

namespace App\Http\Controllers\Api\Chat;

use App\Events\Chat\UserTyping;
use App\Events\PresenceUpdated;
use App\Http\Controllers\Controller;
use App\Models\Chat\Conversation;
use App\Models\Chat\TypingIndicator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PresenceController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function typing(Request $request, Conversation $conversation)
    {
        $this->authorize('participate', $conversation);

        $validated = $request->validate([
            'is_typing' => 'required|boolean',
        ]);

        $cacheKey = "chat.typing.{$conversation->id}.".auth()->id();
        $isTyping = $validated['is_typing'];

        if ($isTyping) {
            // Store typing state for 10 seconds
            Cache::put($cacheKey, true, now()->addSeconds(10));
        } else {
            Cache::forget($cacheKey);
        }

        // Broadcast typing event
        broadcast(new UserTyping(auth()->user(), $conversation->id, $isTyping));

        return response()->json(['status' => 'ok']);
    }

    public function getTyping(Conversation $conversation)
    {
        $this->authorize('view', $conversation);

        $typingUsers = [];
        $participants = $conversation->activeParticipants()->pluck('user_id');

        foreach ($participants as $userId) {
            if ($userId === auth()->id()) {
                continue;
            }

            $cacheKey = "chat.typing.{$conversation->id}.{$userId}";
            if (Cache::has($cacheKey)) {
                $user = \App\Models\User::find($userId);
                if ($user) {
                    $typingUsers[] = [
                        'id' => $user->id,
                        'name' => $user->name,
                    ];
                }
            }
        }

        return response()->json(['typing_users' => $typingUsers]);
    }

    public function updateStatus(Request $request)
    {
        $validated = $request->validate([
            'status' => 'required|in:online,away,busy,offline',
            'last_seen' => 'nullable|date',
        ]);

        $cacheKey = 'user.presence.'.auth()->id();
        $presenceData = [
            'status' => $validated['status'],
            'last_seen' => $validated['last_seen'] ?? now(),
            'updated_at' => now(),
        ];

        // Store presence for 5 minutes
        Cache::put($cacheKey, $presenceData, now()->addMinutes(5));

        // Broadcast presence update
        broadcast(new PresenceUpdated(auth()->user(), $validated['status']));

        return response()->json($presenceData);
    }

    public function getStatus(Request $request)
    {
        $userIds = $request->input('user_ids', []);
        if (! is_array($userIds)) {
            $userIds = [];
        }

        $presences = [];
        foreach ($userIds as $userId) {
            $cacheKey = "user.presence.{$userId}";
            $presence = Cache::get($cacheKey, [
                'status' => 'offline',
                'last_seen' => null,
                'updated_at' => null,
            ]);

            $presences[$userId] = $presence;
        }

        return response()->json(['presences' => $presences]);
    }

    public function heartbeat(Request $request)
    {
        // Simple heartbeat to maintain online status
        $cacheKey = 'user.presence.'.auth()->id();
        $currentPresence = Cache::get($cacheKey, ['status' => 'online']);

        $presenceData = array_merge($currentPresence, [
            'last_seen' => now(),
            'updated_at' => now(),
        ]);

        Cache::put($cacheKey, $presenceData, now()->addMinutes(5));

        return response()->json(['status' => 'ok']);
    }

    public function setTyping(Request $request, Conversation $conversation)
    {
        $this->authorize('participate', $conversation);

        try {
            TypingIndicator::setTyping($conversation->id, auth()->id());

            // Broadcast typing event
            broadcast(new UserTyping(auth()->user(), $conversation->id, true));

            return response()->json(['status' => 'ok']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to set typing status'], 500);
        }
    }

    public function stopTyping(Request $request, Conversation $conversation)
    {
        $this->authorize('participate', $conversation);

        try {
            TypingIndicator::stopTyping($conversation->id, auth()->id());

            // Broadcast stopped typing event
            broadcast(new UserTyping(auth()->user(), $conversation->id, false));

            return response()->json(['status' => 'ok']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to stop typing status'], 500);
        }
    }

    public function getTypingUsers(Conversation $conversation)
    {
        $this->authorize('view', $conversation);

        $typingUsers = TypingIndicator::forConversation($conversation->id)
            ->typing()
            ->with('user:id,name')
            ->where('user_id', '!=', auth()->id())
            ->get()
            ->map(function ($indicator) {
                return [
                    'id' => $indicator->user->id,
                    'name' => $indicator->user->name,
                    'last_typed_at' => $indicator->last_typed_at,
                ];
            });

        return response()->json(['typing_users' => $typingUsers]);
    }
}
