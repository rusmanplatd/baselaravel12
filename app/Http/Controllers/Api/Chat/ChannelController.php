<?php

namespace App\Http\Controllers\Api\Chat;

use App\Http\Controllers\Controller;
use App\Models\Chat\Conversation;
use App\Models\Channel\ChannelCategory;
use App\Models\Channel\ChannelSubscription;
use App\Models\Channel\ChannelStatistic;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ChannelController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
        $this->middleware('throttle:60,1')->except(['index', 'show', 'discover']);
    }

    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        $query = Conversation::channels()
            ->with(['creator', 'categoryInfo', 'activeSubscriptions'])
            ->withCount('activeSubscriptions as subscriber_count')
            ->active();

        // Filter by subscription status
        if ($request->has('subscribed') && $request->boolean('subscribed')) {
            $query->whereHas('activeSubscriptions', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

        // Filter by category
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        // Filter by verification status
        if ($request->has('verified')) {
            $query->where('is_verified', $request->boolean('verified'));
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('description', 'ilike', "%{$search}%")
                  ->orWhere('username', 'ilike', "%{$search}%");
            });
        }

        // Sort
        $sortBy = $request->get('sort', 'created_at');
        $sortDirection = $request->get('direction', 'desc');
        
        $allowedSorts = ['created_at', 'subscriber_count', 'view_count', 'name', 'last_activity_at'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDirection);
        }

        $channels = $query->paginate($request->get('per_page', 15));

        // Add subscription status for authenticated user
        $channels->getCollection()->transform(function ($channel) use ($user) {
            $channel->is_subscribed = $channel->isUserSubscribed($user->id);
            $channel->subscription_status = $channel->activeSubscriptions
                ->where('user_id', $user->id)
                ->first()?->status;
            return $channel;
        });

        return response()->json($channels);
    }

    public function discover(Request $request): JsonResponse
    {
        $query = Conversation::discoverable()
            ->with(['creator', 'categoryInfo'])
            ->withCount('activeSubscriptions as subscriber_count');

        // Filter by category
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        // Popular channels
        if ($request->boolean('popular')) {
            $query->popular();
        }

        // Recently created
        if ($request->boolean('new')) {
            $query->where('created_at', '>=', now()->subWeek())
                  ->orderBy('created_at', 'desc');
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('description', 'ilike', "%{$search}%")
                  ->orWhere('username', 'ilike', "%{$search}%");
            });
        }

        $channels = $query->paginate($request->get('per_page', 20));

        // Add subscription status if user is authenticated
        if (Auth::check()) {
            $user = Auth::user();
            $channels->getCollection()->transform(function ($channel) use ($user) {
                $channel->is_subscribed = $channel->isUserSubscribed($user->id);
                return $channel;
            });
        }

        return response()->json($channels);
    }

    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'nullable|string|max:50|unique:chat_conversations,username|regex:/^[a-z0-9_]+$/',
            'description' => 'nullable|string|max:1000',
            'category' => 'nullable|exists:channel_categories,slug',
            'privacy' => 'required|in:public,private,invite_only',
            'is_broadcast' => 'boolean',
            'allow_anonymous_posts' => 'boolean',
            'show_subscriber_count' => 'boolean',
            'require_join_approval' => 'boolean',
            'avatar_url' => 'nullable|url',
            'welcome_message' => 'nullable|string|max:500',
            'channel_settings' => 'nullable|array',
        ]);

        // Generate username if not provided
        if (empty($validated['username'])) {
            $validated['username'] = Str::slug($validated['name']);
            
            // Ensure uniqueness
            $baseUsername = $validated['username'];
            $counter = 1;
            while (Conversation::where('username', $validated['username'])->exists()) {
                $validated['username'] = $baseUsername . '_' . $counter;
                $counter++;
            }
        }

        $channel = DB::transaction(function () use ($validated, $user) {
            $channel = Conversation::create([
                'name' => $validated['name'],
                'type' => 'channel',
                'username' => $validated['username'],
                'description' => $validated['description'] ?? null,
                'category' => $validated['category'] ?? null,
                'privacy' => $validated['privacy'],
                'is_broadcast' => $validated['is_broadcast'] ?? false,
                'allow_anonymous_posts' => $validated['allow_anonymous_posts'] ?? false,
                'show_subscriber_count' => $validated['show_subscriber_count'] ?? true,
                'require_join_approval' => $validated['require_join_approval'] ?? false,
                'avatar_url' => $validated['avatar_url'] ?? null,
                'welcome_message' => $validated['welcome_message'] ?? null,
                'channel_settings' => $validated['channel_settings'] ?? null,
                'created_by_user_id' => $user->id,
                'organization_id' => $user->current_organization_id,
            ]);

            // Add creator as admin participant
            $channel->addParticipant($user->id, [
                'role' => 'admin',
                'permissions' => ['*'], // All permissions
            ]);

            // Auto-subscribe creator
            $channel->subscribeUser($user->id);
            $channel->incrementSubscriberCount();

            return $channel;
        });

        $channel->load(['creator', 'categoryInfo']);
        $channel->is_subscribed = true;
        
        return response()->json([
            'message' => 'Channel created successfully',
            'channel' => $channel,
        ], 201);
    }

    public function show(string $id, Request $request): JsonResponse
    {
        $channel = Conversation::channels()
            ->with(['creator', 'categoryInfo'])
            ->withCount('activeSubscriptions as subscriber_count')
            ->findOrFail($id);

        // Check if user can view the channel
        if (!$this->canViewChannel($channel)) {
            return response()->json(['message' => 'Channel not found'], 404);
        }

        // Record view if user is authenticated
        if (Auth::check()) {
            $user = Auth::user();
            $channel->recordView($user->id, $request->ip(), $request->userAgent());
            $channel->is_subscribed = $channel->isUserSubscribed($user->id);
            
            // Update last viewed for subscriber
            $subscription = $channel->activeSubscriptions()
                ->where('user_id', $user->id)
                ->first();
            if ($subscription) {
                $subscription->updateLastViewed();
            }
        } else {
            // Anonymous view
            $channel->recordView(null, $request->ip(), $request->userAgent());
        }

        // Get recent statistics
        $channel->stats = $channel->getChannelStatsForPeriod('week');
        
        return response()->json($channel);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $user = Auth::user();
        $channel = Conversation::channels()->findOrFail($id);

        // Check permissions
        if (!$channel->isUserAdmin($user->id)) {
            return response()->json(['message' => 'Insufficient permissions'], 403);
        }

        $validated = $request->validate([
            'name' => 'string|max:255',
            'username' => [
                'string',
                'max:50',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('chat_conversations')->ignore($channel->id)
            ],
            'description' => 'nullable|string|max:1000',
            'category' => 'nullable|exists:channel_categories,slug',
            'privacy' => 'in:public,private,invite_only',
            'is_broadcast' => 'boolean',
            'allow_anonymous_posts' => 'boolean',
            'show_subscriber_count' => 'boolean',
            'require_join_approval' => 'boolean',
            'avatar_url' => 'nullable|url',
            'welcome_message' => 'nullable|string|max:500',
            'channel_settings' => 'nullable|array',
        ]);

        $channel->update($validated);
        
        $channel->logAdminAction($user->id, 'channel_updated', [
            'description' => 'Channel settings updated',
            'previous_values' => $channel->getOriginal(),
            'action_data' => $validated,
        ]);

        $channel->load(['creator', 'categoryInfo']);
        
        return response()->json([
            'message' => 'Channel updated successfully',
            'channel' => $channel,
        ]);
    }

    public function subscribe(Request $request, string $id): JsonResponse
    {
        $user = Auth::user();
        $channel = Conversation::channels()->findOrFail($id);

        // Check if channel is public or user has permission to join
        if (!$this->canJoinChannel($channel, $user->id)) {
            return response()->json(['message' => 'Cannot subscribe to this channel'], 403);
        }

        // Check if already subscribed
        if ($channel->isUserSubscribed($user->id)) {
            return response()->json(['message' => 'Already subscribed to this channel'], 400);
        }

        DB::transaction(function () use ($channel, $user) {
            $channel->subscribeUser($user->id);
            $channel->incrementSubscriberCount();
            ChannelStatistic::recordSubscription($channel->id);
        });

        return response()->json(['message' => 'Successfully subscribed to channel']);
    }

    public function unsubscribe(Request $request, string $id): JsonResponse
    {
        $user = Auth::user();
        $channel = Conversation::channels()->findOrFail($id);

        if (!$channel->isUserSubscribed($user->id)) {
            return response()->json(['message' => 'Not subscribed to this channel'], 400);
        }

        DB::transaction(function () use ($channel, $user) {
            $channel->unsubscribeUser($user->id);
            ChannelStatistic::recordUnsubscription($channel->id);
        });

        return response()->json(['message' => 'Successfully unsubscribed from channel']);
    }

    public function subscribers(Request $request, string $id): JsonResponse
    {
        $user = Auth::user();
        $channel = Conversation::channels()->findOrFail($id);

        // Only admins can view subscriber list
        if (!$channel->isUserAdmin($user->id)) {
            return response()->json(['message' => 'Insufficient permissions'], 403);
        }

        $subscribers = $channel->activeSubscriptions()
            ->with('user')
            ->orderBy('subscribed_at', 'desc')
            ->paginate($request->get('per_page', 50));

        return response()->json($subscribers);
    }

    public function statistics(Request $request, string $id): JsonResponse
    {
        $user = Auth::user();
        $channel = Conversation::channels()->findOrFail($id);

        // Only admins can view detailed statistics
        if (!$channel->isUserAdmin($user->id)) {
            return response()->json(['message' => 'Insufficient permissions'], 403);
        }

        $period = $request->get('period', 'week');
        $stats = $channel->getChannelStatsForPeriod($period);

        return response()->json($stats);
    }

    public function verify(Request $request, string $id): JsonResponse
    {
        $user = Auth::user();
        
        // Only system admins can verify channels (implement your own logic)
        if (!$user->hasRole('admin')) {
            return response()->json(['message' => 'Insufficient permissions'], 403);
        }

        $channel = Conversation::channels()->findOrFail($id);
        $channel->verify();

        $channel->logAdminAction($user->id, 'channel_verified', [
            'description' => 'Channel verified',
        ]);

        return response()->json(['message' => 'Channel verified successfully']);
    }

    public function categories(): JsonResponse
    {
        $categories = ChannelCategory::getCategoriesWithChannels();
        return response()->json($categories);
    }

    protected function canViewChannel(Conversation $channel): bool
    {
        // Public channels can be viewed by anyone
        if ($channel->isPublic()) {
            return true;
        }

        // Private channels require subscription or admin access
        if (Auth::check()) {
            $user = Auth::user();
            return $channel->isUserSubscribed($user->id) || $channel->isUserAdmin($user->id);
        }

        return false;
    }

    protected function canJoinChannel(Conversation $channel, string $userId): bool
    {
        // Check if channel is public
        if ($channel->isPublic() && !$channel->requiresJoinApproval()) {
            return true;
        }

        // For private channels or channels requiring approval, implement additional logic
        // This could involve invitation links, admin approval, etc.
        return false;
    }
}