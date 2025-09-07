<?php

namespace App\Http\Controllers\Api\Chat;

use App\Http\Controllers\Controller;
use App\Models\Chat\Conversation;
use App\Models\Chat\Message;
use App\Models\Channel\ChannelBroadcast;
use App\Models\Channel\ChannelStatistic;
use App\Models\Channel\ChannelSubscription;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ChannelBroadcastController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
        $this->middleware('throttle:30,1');
    }

    public function index(Request $request, string $channelId): JsonResponse
    {
        $user = Auth::user();
        $channel = Conversation::channels()->findOrFail($channelId);

        // Only admins can view broadcasts
        if (!$channel->isUserAdmin($user->id)) {
            return response()->json(['message' => 'Insufficient permissions'], 403);
        }

        $query = $channel->broadcasts()
            ->with(['creator', 'message'])
            ->orderBy('created_at', 'desc');

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->filled('from_date')) {
            $query->where('created_at', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->where('created_at', '<=', $request->to_date);
        }

        $broadcasts = $query->paginate($request->get('per_page', 20));

        return response()->json($broadcasts);
    }

    public function store(Request $request, string $channelId): JsonResponse
    {
        $user = Auth::user();
        $channel = Conversation::channels()->findOrFail($channelId);

        // Check permissions - only admins can create broadcasts
        if (!$channel->isUserAdmin($user->id)) {
            return response()->json(['message' => 'Insufficient permissions'], 403);
        }

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'content' => 'required|string|max:4000',
            'media_attachments' => 'nullable|array',
            'media_attachments.*' => 'string', // File URLs or IDs
            'scheduled_at' => 'nullable|date|after:now',
            'broadcast_settings' => 'nullable|array',
            'broadcast_settings.silent' => 'boolean',
            'broadcast_settings.pin_message' => 'boolean',
            'broadcast_settings.disable_notifications' => 'boolean',
        ]);

        $broadcast = DB::transaction(function () use ($validated, $channel, $user) {
            $broadcast = $channel->createBroadcast($user->id, $validated);

            // If not scheduled, send immediately
            if (!isset($validated['scheduled_at'])) {
                $this->sendBroadcast($broadcast);
            } else {
                $broadcast->schedule(Carbon::parse($validated['scheduled_at']));
            }

            return $broadcast;
        });

        $broadcast->load(['creator', 'message']);

        return response()->json([
            'message' => $broadcast->isScheduled() ? 'Broadcast scheduled successfully' : 'Broadcast sent successfully',
            'broadcast' => $broadcast,
        ], 201);
    }

    public function show(Request $request, string $channelId, string $broadcastId): JsonResponse
    {
        $user = Auth::user();
        $channel = Conversation::channels()->findOrFail($channelId);
        $broadcast = $channel->broadcasts()->with(['creator', 'message'])->findOrFail($broadcastId);

        // Only admins can view broadcast details
        if (!$channel->isUserAdmin($user->id)) {
            return response()->json(['message' => 'Insufficient permissions'], 403);
        }

        return response()->json($broadcast);
    }

    public function update(Request $request, string $channelId, string $broadcastId): JsonResponse
    {
        $user = Auth::user();
        $channel = Conversation::channels()->findOrFail($channelId);
        $broadcast = $channel->broadcasts()->findOrFail($broadcastId);

        // Only admins can edit broadcasts
        if (!$channel->isUserAdmin($user->id)) {
            return response()->json(['message' => 'Insufficient permissions'], 403);
        }

        // Can only edit drafts or scheduled broadcasts
        if (!$broadcast->canBeEdited()) {
            return response()->json(['message' => 'Cannot edit sent broadcasts'], 400);
        }

        $validated = $request->validate([
            'title' => 'string|max:255',
            'content' => 'string|max:4000',
            'media_attachments' => 'nullable|array',
            'scheduled_at' => 'nullable|date|after:now',
            'broadcast_settings' => 'nullable|array',
        ]);

        $broadcast->update($validated);

        // Update schedule if needed
        if (isset($validated['scheduled_at'])) {
            $broadcast->schedule(Carbon::parse($validated['scheduled_at']));
        }

        $broadcast->load(['creator', 'message']);

        return response()->json([
            'message' => 'Broadcast updated successfully',
            'broadcast' => $broadcast,
        ]);
    }

    public function destroy(Request $request, string $channelId, string $broadcastId): JsonResponse
    {
        $user = Auth::user();
        $channel = Conversation::channels()->findOrFail($channelId);
        $broadcast = $channel->broadcasts()->findOrFail($broadcastId);

        // Only admins can delete broadcasts
        if (!$channel->isUserAdmin($user->id)) {
            return response()->json(['message' => 'Insufficient permissions'], 403);
        }

        // Can only delete unsent broadcasts
        if (!$broadcast->canBeDeleted()) {
            return response()->json(['message' => 'Cannot delete sent broadcasts'], 400);
        }

        $broadcast->delete();

        $channel->logAdminAction($user->id, 'broadcast_deleted', [
            'description' => 'Broadcast deleted',
            'action_data' => ['broadcast_id' => $broadcast->id, 'title' => $broadcast->title],
        ]);

        return response()->json(['message' => 'Broadcast deleted successfully']);
    }

    public function send(Request $request, string $channelId, string $broadcastId): JsonResponse
    {
        $user = Auth::user();
        $channel = Conversation::channels()->findOrFail($channelId);
        $broadcast = $channel->broadcasts()->findOrFail($broadcastId);

        // Only admins can send broadcasts
        if (!$channel->isUserAdmin($user->id)) {
            return response()->json(['message' => 'Insufficient permissions'], 403);
        }

        // Can only send drafts or scheduled broadcasts
        if (!$broadcast->canBeSent()) {
            return response()->json(['message' => 'Cannot send this broadcast'], 400);
        }

        DB::transaction(function () use ($broadcast) {
            $this->sendBroadcast($broadcast);
        });

        $broadcast->load(['creator', 'message']);

        return response()->json([
            'message' => 'Broadcast sent successfully',
            'broadcast' => $broadcast,
        ]);
    }

    public function duplicate(Request $request, string $channelId, string $broadcastId): JsonResponse
    {
        $user = Auth::user();
        $channel = Conversation::channels()->findOrFail($channelId);
        $broadcast = $channel->broadcasts()->findOrFail($broadcastId);

        // Only admins can duplicate broadcasts
        if (!$channel->isUserAdmin($user->id)) {
            return response()->json(['message' => 'Insufficient permissions'], 403);
        }

        $duplicated = $broadcast->duplicate();
        $duplicated->load(['creator']);

        return response()->json([
            'message' => 'Broadcast duplicated successfully',
            'broadcast' => $duplicated,
        ], 201);
    }

    public function analytics(Request $request, string $channelId, string $broadcastId): JsonResponse
    {
        $user = Auth::user();
        $channel = Conversation::channels()->findOrFail($channelId);
        $broadcast = $channel->broadcasts()->with('message')->findOrFail($broadcastId);

        // Only admins can view analytics
        if (!$channel->isUserAdmin($user->id)) {
            return response()->json(['message' => 'Insufficient permissions'], 403);
        }

        if (!$broadcast->isSent() || !$broadcast->message) {
            return response()->json(['message' => 'Broadcast not sent yet'], 400);
        }

        $analytics = [
            'broadcast_info' => [
                'title' => $broadcast->title,
                'sent_at' => $broadcast->sent_at,
                'recipient_count' => $broadcast->recipient_count,
                'delivered_count' => $broadcast->delivered_count,
                'read_count' => $broadcast->read_count,
                'delivery_rate' => $broadcast->getDeliveryRate(),
                'read_rate' => $broadcast->getReadRate(),
            ],
            'engagement' => [
                'views' => $broadcast->message->views_count ?? 0,
                'reactions' => $broadcast->message->reactions()->count(),
                'forwards' => $broadcast->message->forward_count ?? 0,
                'replies' => $broadcast->message->replies()->count(),
            ],
        ];

        return response()->json($analytics);
    }

    protected function sendBroadcast(ChannelBroadcast $broadcast): void
    {
        $channel = $broadcast->channel;
        
        // Get active subscribers
        $subscriberCount = $channel->activeSubscriptions()
            ->withNotifications()
            ->notMuted()
            ->count();

        // Create the actual message in the channel
        $message = $channel->messages()->create([
            'sender_user_id' => $broadcast->created_by_user_id,
            'content' => $broadcast->content,
            'message_type' => 'broadcast',
            'attachments' => $broadcast->media_attachments,
            'broadcast_id' => $broadcast->id,
            'is_pinned' => $broadcast->broadcast_settings['pin_message'] ?? false,
        ]);

        // Update broadcast with message reference
        $broadcast->update([
            'message_id' => $message->id,
            'recipient_count' => $subscriberCount,
        ]);

        // Mark as sent
        $broadcast->markAsSent();

        // Record statistics
        ChannelStatistic::recordMessage($channel->id);
        
        // Update channel's last broadcast time
        $channel->update(['last_broadcast_at' => now()]);

        // Log admin action
        $channel->logAdminAction($broadcast->created_by_user_id, 'broadcast_sent', [
            'description' => 'Broadcast message sent',
            'action_data' => [
                'broadcast_id' => $broadcast->id,
                'message_id' => $message->id,
                'recipient_count' => $subscriberCount,
            ],
        ]);

        // TODO: Trigger real-time notifications to subscribers
        // This would integrate with your broadcasting system (Pusher, Socket.io, etc.)
    }
}