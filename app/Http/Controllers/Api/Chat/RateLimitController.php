<?php

namespace App\Http\Controllers\Api\Chat;

use App\Http\Controllers\Controller;
use App\Models\Chat\IpRestriction;
use App\Models\Chat\RateLimit;
use App\Models\Chat\RateLimitConfig;
use App\Models\Chat\UserPenalty;
use App\Services\RateLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class RateLimitController extends Controller
{
    protected RateLimitService $rateLimitService;

    public function __construct(RateLimitService $rateLimitService)
    {
        $this->rateLimitService = $rateLimitService;
    }

    public function getConfigs(): JsonResponse
    {
        $user = Auth::user();

        if (! $user->can('manage_rate_limits')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $configs = RateLimitConfig::active()
            ->orderBy('action_name')
            ->orderBy('scope')
            ->get();

        return response()->json(['configs' => $configs]);
    }

    public function createConfig(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (! $user->can('manage_rate_limits')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'action_name' => 'required|string|max:100',
            'scope' => 'required|string|in:global,per_user,per_ip,per_conversation',
            'max_attempts' => 'required|integer|min:1|max:1000',
            'window_seconds' => 'required|integer|min:1|max:86400',
            'penalty_duration_seconds' => 'nullable|integer|min:0|max:2592000',
            'escalation_rules' => 'nullable|array',
            'description' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        // Check for duplicate action/scope combination
        $existing = RateLimitConfig::where('action_name', $data['action_name'])
            ->where('scope', $data['scope'])
            ->exists();

        if ($existing) {
            return response()->json([
                'error' => 'Rate limit config already exists for this action and scope',
            ], 409);
        }

        $config = RateLimitConfig::create($data);

        return response()->json([
            'message' => 'Rate limit config created successfully',
            'config' => $config,
        ], 201);
    }

    public function updateConfig(Request $request, string $configId): JsonResponse
    {
        $user = Auth::user();

        if (! $user->can('manage_rate_limits')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $config = RateLimitConfig::find($configId);
        if (! $config) {
            return response()->json(['error' => 'Config not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'max_attempts' => 'sometimes|integer|min:1|max:1000',
            'window_seconds' => 'sometimes|integer|min:1|max:86400',
            'penalty_duration_seconds' => 'sometimes|nullable|integer|min:0|max:2592000',
            'escalation_rules' => 'sometimes|nullable|array',
            'is_active' => 'sometimes|boolean',
            'description' => 'sometimes|nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors(),
            ], 422);
        }

        $config->update($validator->validated());

        return response()->json([
            'message' => 'Rate limit config updated successfully',
            'config' => $config->fresh(),
        ]);
    }

    public function deleteConfig(string $configId): JsonResponse
    {
        $user = Auth::user();

        if (! $user->can('manage_rate_limits')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $config = RateLimitConfig::find($configId);
        if (! $config) {
            return response()->json(['error' => 'Config not found'], 404);
        }

        $config->delete();

        return response()->json([
            'message' => 'Rate limit config deleted successfully',
        ]);
    }

    public function getUserPenalties(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (! $user->can('manage_penalties')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $query = UserPenalty::with(['user', 'appliedBy']);

        if ($request->has('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->has('penalty_type')) {
            $query->where('penalty_type', $request->input('penalty_type'));
        }

        if ($request->has('is_active')) {
            if ($request->boolean('is_active')) {
                $query->active();
            } else {
                $query->expired();
            }
        }

        $penalties = $query->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'penalties' => $penalties->items(),
            'pagination' => [
                'current_page' => $penalties->currentPage(),
                'per_page' => $penalties->perPage(),
                'total' => $penalties->total(),
                'last_page' => $penalties->lastPage(),
            ],
        ]);
    }

    public function applyUserPenalty(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (! $user->can('apply_penalties')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|string|exists:sys_users,id',
            'penalty_type' => 'required|string|in:rate_limit,message_limit,file_limit,temporary_ban',
            'reason' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'restrictions' => 'required|array',
            'severity_level' => 'required|integer|min:1|max:5',
            'duration_hours' => 'nullable|integer|min:1|max:8760',
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        $penalty = UserPenalty::create([
            'user_id' => $data['user_id'],
            'penalty_type' => $data['penalty_type'],
            'reason' => $data['reason'],
            'description' => $data['description'] ?? null,
            'restrictions' => $data['restrictions'],
            'severity_level' => $data['severity_level'],
            'starts_at' => now(),
            'expires_at' => isset($data['duration_hours'])
                ? now()->addHours($data['duration_hours'])
                : null,
            'is_active' => true,
            'applied_by' => $user->id,
            'admin_notes' => $data['admin_notes'] ?? null,
        ]);

        return response()->json([
            'message' => 'User penalty applied successfully',
            'penalty' => $penalty->load(['user', 'appliedBy']),
        ], 201);
    }

    public function getIpRestrictions(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (! $user->can('manage_ip_restrictions')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $query = IpRestriction::with('appliedBy');

        if ($request->has('ip_address')) {
            $query->where('ip_address', $request->input('ip_address'));
        }

        if ($request->has('restriction_type')) {
            $query->where('restriction_type', $request->input('restriction_type'));
        }

        if ($request->has('is_active')) {
            if ($request->boolean('is_active')) {
                $query->active();
            } else {
                $query->expired();
            }
        }

        $restrictions = $query->orderBy('last_violation_at', 'desc')
            ->paginate(20);

        return response()->json([
            'restrictions' => $restrictions->items(),
            'pagination' => [
                'current_page' => $restrictions->currentPage(),
                'per_page' => $restrictions->perPage(),
                'total' => $restrictions->total(),
                'last_page' => $restrictions->lastPage(),
            ],
        ]);
    }

    public function getRateLimitStatus(Request $request): JsonResponse
    {
        $user = Auth::user();
        $action = $request->input('action', 'messages');

        // Generate appropriate key based on user
        $key = $user ? "user:{$user->id}" : "ip:{$request->ip()}";

        $result = $this->rateLimitService->checkRateLimit($action, $key, $user, $request->ip());

        return response()->json([
            'allowed' => $result['allowed'],
            'current_hits' => $result['current_hits'] ?? 0,
            'max_attempts' => $result['max_attempts'] ?? 0,
            'remaining' => $result['remaining'] ?? 0,
            'window_seconds' => $result['window_seconds'] ?? 0,
            'reset_at' => $result['reset_at'] ?? null,
            'reason' => $result['reason'] ?? null,
        ]);
    }

    public function getSystemStats(): JsonResponse
    {
        $user = Auth::user();

        if (! $user->can('view_rate_limit_stats')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $stats = [
            'active_rate_limits' => RateLimit::active()->count(),
            'active_user_penalties' => UserPenalty::active()->count(),
            'active_ip_restrictions' => IpRestriction::active()->count(),
            'rate_limits_last_hour' => RateLimit::where('created_at', '>=', now()->subHour())->count(),
            'penalties_applied_today' => UserPenalty::whereDate('created_at', today())->count(),
            'most_limited_actions' => RateLimit::selectRaw('action, count(*) as hits')
                ->where('created_at', '>=', now()->subDay())
                ->groupBy('action')
                ->orderBy('hits', 'desc')
                ->limit(5)
                ->get(),
            'penalty_types_distribution' => UserPenalty::selectRaw('penalty_type, count(*) as count')
                ->where('created_at', '>=', now()->subWeek())
                ->groupBy('penalty_type')
                ->pluck('count', 'penalty_type'),
        ];

        return response()->json(['stats' => $stats]);
    }
}
