<?php

namespace App\Http\Controllers\Api\Chat;

use App\Http\Controllers\Controller;
use App\Models\Chat\Conversation;
use App\Models\Chat\Participant;
use App\Models\Channel\ChannelSubscription;
use App\Models\Channel\ChannelStatistic;
use App\Models\Channel\ChannelCategory;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ChannelManagementController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
        $this->middleware('throttle:60,1');
    }

    public function addAdmin(Request $request, string $channelId): JsonResponse
    {
        $user = Auth::user();
        $channel = Conversation::channels()->findOrFail($channelId);

        // Only admins can add other admins
        if (!$channel->isUserAdmin($user->id)) {
            return response()->json(['message' => 'Insufficient permissions'], 403);
        }

        $validated = $request->validate([
            'user_id' => 'required|exists:sys_users,id',
            'permissions' => 'nullable|array',
        ]);

        $targetUserId = $validated['user_id'];

        // Check if user is already a participant
        $participant = $channel->participants()->where('user_id', $targetUserId)->active()->first();

        DB::transaction(function () use ($channel, $targetUserId, $validated, $participant, $user) {
            if ($participant) {
                // Update existing participant to admin
                $participant->update([
                    'role' => 'admin',
                    'permissions' => $validated['permissions'] ?? ['*'],
                ]);
            } else {
                // Add as new admin participant
                $participant = $channel->addParticipant($targetUserId, [
                    'role' => 'admin',
                    'permissions' => $validated['permissions'] ?? ['*'],
                ]);

                // Also subscribe them to the channel
                $channel->subscribeUser($targetUserId);
                $channel->incrementSubscriberCount();
            }

            $channel->logAdminAction($user->id, 'admin_added', [
                'target_user_id' => $targetUserId,
                'description' => 'User promoted to admin',
                'action_data' => ['permissions' => $validated['permissions'] ?? ['*']],
            ]);
        });

        return response()->json(['message' => 'Admin added successfully']);
    }

    public function removeAdmin(Request $request, string $channelId, string $userId): JsonResponse
    {
        $currentUser = Auth::user();
        $channel = Conversation::channels()->findOrFail($channelId);

        // Only admins can remove other admins
        if (!$channel->isUserAdmin($currentUser->id)) {
            return response()->json(['message' => 'Insufficient permissions'], 403);
        }

        // Cannot remove the channel creator unless there are other admins
        if ($channel->created_by_user_id === $userId) {
            $adminCount = $channel->getAdminCount();
            if ($adminCount <= 1) {
                return response()->json(['message' => 'Cannot remove the last admin'], 400);
            }
        }

        $participant = $channel->participants()
            ->where('user_id', $userId)
            ->active()
            ->first();

        if (!$participant || !$participant->isAdmin()) {
            return response()->json(['message' => 'User is not an admin'], 400);
        }

        DB::transaction(function () use ($participant, $channel, $currentUser, $userId) {
            // Demote to regular member
            $participant->update([
                'role' => 'member',
                'permissions' => null,
            ]);

            $channel->logAdminAction($currentUser->id, 'admin_removed', [
                'target_user_id' => $userId,
                'description' => 'User demoted from admin',
            ]);
        });

        return response()->json(['message' => 'Admin removed successfully']);
    }

    public function banUser(Request $request, string $channelId): JsonResponse
    {
        $user = Auth::user();
        $channel = Conversation::channels()->findOrFail($channelId);

        // Only admins can ban users
        if (!$channel->isUserAdmin($user->id)) {
            return response()->json(['message' => 'Insufficient permissions'], 403);
        }

        $validated = $request->validate([
            'user_id' => 'required|exists:sys_users,id',
            'reason' => 'nullable|string|max:500',
            'duration' => 'nullable|in:1h,24h,7d,30d,permanent',
        ]);

        $targetUserId = $validated['user_id'];

        // Cannot ban other admins
        if ($channel->isUserAdmin($targetUserId)) {
            return response()->json(['message' => 'Cannot ban channel admins'], 400);
        }

        $expiresAt = null;
        if (isset($validated['duration']) && $validated['duration'] !== 'permanent') {
            $expiresAt = match($validated['duration']) {
                '1h' => now()->addHour(),
                '24h' => now()->addDay(),
                '7d' => now()->addWeek(),
                '30d' => now()->addMonth(),
            };
        }

        DB::transaction(function () use ($channel, $user, $targetUserId, $validated, $expiresAt) {
            // Remove from participants and unsubscribe
            $channel->removeParticipant($targetUserId);
            $channel->unsubscribeUser($targetUserId);

            // Add to banned users (assuming you have this relationship)
            $channel->banUser($targetUserId, $user->id, [
                'reason' => $validated['reason'] ?? null,
                'expires_at' => $expiresAt,
                'is_permanent' => $validated['duration'] === 'permanent',
            ]);

            $channel->logAdminAction($user->id, 'user_banned', [
                'target_user_id' => $targetUserId,
                'description' => 'User banned from channel',
                'action_data' => $validated,
            ]);
        });

        return response()->json(['message' => 'User banned successfully']);
    }

    public function unbanUser(Request $request, string $channelId, string $userId): JsonResponse
    {
        $currentUser = Auth::user();
        $channel = Conversation::channels()->findOrFail($channelId);

        // Only admins can unban users
        if (!$channel->isUserAdmin($currentUser->id)) {
            return response()->json(['message' => 'Insufficient permissions'], 403);
        }

        if (!$channel->isUserBanned($userId)) {
            return response()->json(['message' => 'User is not banned'], 400);
        }

        DB::transaction(function () use ($channel, $currentUser, $userId) {
            $channel->unbanUser($userId, $currentUser->id);

            $channel->logAdminAction($currentUser->id, 'user_unbanned', [
                'target_user_id' => $userId,
                'description' => 'User unbanned from channel',
            ]);
        });

        return response()->json(['message' => 'User unbanned successfully']);
    }

    public function deleteChannel(Request $request, string $channelId): JsonResponse
    {
        $user = Auth::user();
        $channel = Conversation::channels()->findOrFail($channelId);

        // Only the creator or system admins can delete channels
        if ($channel->created_by_user_id !== $user->id && !$user->hasRole('admin')) {
            return response()->json(['message' => 'Insufficient permissions'], 403);
        }

        $validated = $request->validate([
            'confirmation' => 'required|string|in:DELETE',
            'reason' => 'nullable|string|max:500',
        ]);

        DB::transaction(function () use ($channel, $user, $validated) {
            // Log the deletion
            $channel->logAdminAction($user->id, 'channel_deleted', [
                'description' => 'Channel deleted',
                'action_data' => ['reason' => $validated['reason'] ?? 'No reason provided'],
            ]);

            // Soft delete the channel
            $channel->delete();
        });

        return response()->json(['message' => 'Channel deleted successfully']);
    }

    public function transferOwnership(Request $request, string $channelId): JsonResponse
    {
        $currentUser = Auth::user();
        $channel = Conversation::channels()->findOrFail($channelId);

        // Only the current owner can transfer ownership
        if ($channel->created_by_user_id !== $currentUser->id) {
            return response()->json(['message' => 'Only the channel owner can transfer ownership'], 403);
        }

        $validated = $request->validate([
            'new_owner_id' => 'required|exists:sys_users,id',
            'confirmation' => 'required|string|in:TRANSFER',
        ]);

        $newOwnerId = $validated['new_owner_id'];

        // New owner must be an existing admin
        if (!$channel->isUserAdmin($newOwnerId)) {
            return response()->json(['message' => 'New owner must be a channel admin'], 400);
        }

        DB::transaction(function () use ($channel, $currentUser, $newOwnerId) {
            $channel->update(['created_by_user_id' => $newOwnerId]);

            $channel->logAdminAction($currentUser->id, 'ownership_transferred', [
                'target_user_id' => $newOwnerId,
                'description' => 'Channel ownership transferred',
            ]);
        });

        return response()->json(['message' => 'Ownership transferred successfully']);
    }

    public function getAdmins(Request $request, string $channelId): JsonResponse
    {
        $user = Auth::user();
        $channel = Conversation::channels()->findOrFail($channelId);

        // Only admins can view admin list
        if (!$channel->isUserAdmin($user->id)) {
            return response()->json(['message' => 'Insufficient permissions'], 403);
        }

        $admins = $channel->activeParticipants()
            ->admins()
            ->with('user')
            ->get()
            ->map(function ($participant) use ($channel) {
                return [
                    'id' => $participant->user->id,
                    'name' => $participant->user->name,
                    'email' => $participant->user->email,
                    'role' => $participant->role,
                    'permissions' => $participant->permissions,
                    'joined_at' => $participant->joined_at,
                    'is_owner' => $participant->user->id === $channel->created_by_user_id,
                ];
            });

        return response()->json($admins);
    }

    public function getBannedUsers(Request $request, string $channelId): JsonResponse
    {
        $user = Auth::user();
        $channel = Conversation::channels()->findOrFail($channelId);

        // Only admins can view banned users
        if (!$channel->isUserAdmin($user->id)) {
            return response()->json(['message' => 'Insufficient permissions'], 403);
        }

        $bannedUsers = $channel->bannedUsers()
            ->with(['user', 'bannedBy'])
            ->whereNull('unbanned_at')
            ->orderBy('banned_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json($bannedUsers);
    }

    public function updateChannelSettings(Request $request, string $channelId): JsonResponse
    {
        $user = Auth::user();
        $channel = Conversation::channels()->findOrFail($channelId);

        // Only admins can update settings
        if (!$channel->isUserAdmin($user->id)) {
            return response()->json(['message' => 'Insufficient permissions'], 403);
        }

        $validated = $request->validate([
            'channel_settings' => 'required|array',
            'channel_settings.auto_delete_messages' => 'nullable|integer|min:1|max:365',
            'channel_settings.slow_mode_interval' => 'nullable|integer|min:0|max:3600',
            'channel_settings.link_preview' => 'boolean',
            'channel_settings.forward_from_channel' => 'boolean',
            'channel_settings.allow_comments' => 'boolean',
        ]);

        $previousSettings = $channel->channel_settings;
        $channel->update(['channel_settings' => $validated['channel_settings']]);

        $channel->logAdminAction($user->id, 'settings_updated', [
            'description' => 'Channel settings updated',
            'previous_values' => ['channel_settings' => $previousSettings],
            'action_data' => $validated,
        ]);

        return response()->json([
            'message' => 'Channel settings updated successfully',
            'channel_settings' => $channel->channel_settings,
        ]);
    }

    public function exportData(Request $request, string $channelId): JsonResponse
    {
        $user = Auth::user();
        $channel = Conversation::channels()->findOrFail($channelId);

        // Only channel owner can export data
        if ($channel->created_by_user_id !== $user->id) {
            return response()->json(['message' => 'Only channel owner can export data'], 403);
        }

        $exportData = [
            'channel_info' => [
                'name' => $channel->name,
                'username' => $channel->username,
                'description' => $channel->description,
                'category' => $channel->category,
                'created_at' => $channel->created_at,
                'subscriber_count' => $channel->getSubscriberCount(),
                'total_messages' => $channel->messages()->count(),
            ],
            'statistics' => $channel->getChannelStatsForPeriod('all'),
            'subscribers_count' => $channel->activeSubscriptions()->count(),
            'admins_count' => $channel->getAdminCount(),
            'broadcasts_sent' => $channel->broadcasts()->sent()->count(),
        ];

        $channel->logAdminAction($user->id, 'data_exported', [
            'description' => 'Channel data exported',
        ]);

        return response()->json($exportData);
    }
}