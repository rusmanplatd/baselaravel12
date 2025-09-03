<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UserApiController extends Controller
{
    /**
     * Get paginated users list with filtering and sorting
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::query()
            ->with(['roles'])
            ->withCount('roles');

        // Apply filters
        $this->applyFilters($query, $request);

        // Apply sorting
        $sortField = $request->input('sort', 'name');
        $sortDirection = 'asc';
        
        // Handle descending sort (prefix with -)
        if (str_starts_with($sortField, '-')) {
            $sortField = substr($sortField, 1);
            $sortDirection = 'desc';
        }

        // Map frontend sort fields to database columns
        $sortableFields = [
            'name' => 'name',
            'email' => 'email',
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
            'roles_count' => 'roles_count',
            'email_verified_at' => 'email_verified_at',
        ];

        $dbSortField = $sortableFields[$sortField] ?? 'name';
        $query->orderBy($dbSortField, $sortDirection);

        // Get pagination parameters
        $perPage = min(100, max(5, (int) $request->input('per_page', 15)));
        
        // Paginate results
        $users = $query->paginate($perPage);

        // Transform the data
        $users->getCollection()->transform(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
                'roles_count' => $user->roles_count,
                'roles' => $user->roles->map(function ($role) {
                    return [
                        'id' => $role->id,
                        'name' => $role->name,
                    ];
                }),
                'created_at' => $user->created_at->toISOString(),
                'updated_at' => $user->updated_at->toISOString(),
            ];
        });

        return response()->json($users);
    }

    /**
     * Apply filters to the query
     */
    private function applyFilters(Builder $query, Request $request): void
    {
        // Text search filters
        if ($request->filled('filter.name')) {
            $query->where('name', 'ILIKE', '%' . $request->input('filter.name') . '%');
        }

        if ($request->filled('filter.email')) {
            $query->where('email', 'ILIKE', '%' . $request->input('filter.email') . '%');
        }

        // Email verification status filter
        if ($request->filled('filter.email_verified')) {
            $verified = $request->input('filter.email_verified');
            if ($verified === '1' || $verified === 'true') {
                $query->whereNotNull('email_verified_at');
            } elseif ($verified === '0' || $verified === 'false') {
                $query->whereNull('email_verified_at');
            }
        }

        // Role count filter
        if ($request->filled('filter.has_roles')) {
            $hasRoles = $request->input('filter.has_roles');
            if ($hasRoles === '1' || $hasRoles === 'true') {
                $query->has('roles');
            } elseif ($hasRoles === '0' || $hasRoles === 'false') {
                $query->doesntHave('roles');
            }
        }

        // Date range filters
        if ($request->filled('filter.created_after')) {
            $query->where('created_at', '>=', $request->input('filter.created_after'));
        }

        if ($request->filled('filter.created_before')) {
            $query->where('created_at', '<=', $request->input('filter.created_before'));
        }
    }

    /**
     * Search for users by name or email
     *
     * @throws ValidationException
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:2|max:100',
            'limit' => 'sometimes|integer|min:1|max:50',
        ]);

        $query = $request->input('q');
        $limit = $request->input('limit', 10);

        // Search users by name or email
        $users = User::query()
            ->where(function (Builder $q) use ($query) {
                $q->where('name', 'ILIKE', "%{$query}%")
                    ->orWhere('email', 'ILIKE', "%{$query}%");
            })
            ->where('email_verified_at', '!=', null) // Only verified users
            ->select(['id', 'name', 'email', 'avatar'])
            ->orderBy('name')
            ->limit($limit)
            ->get();

        // Transform the data for frontend
        $transformedUsers = $users->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar ?? null,
            ];
        });

        return response()->json($transformedUsers);
    }

    /**
     * Get user suggestions for chat (excluding current user)
     *
     * @throws ValidationException
     */
    public function suggestions(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'sometimes|string|min:2|max:100',
            'limit' => 'sometimes|integer|min:1|max:20',
        ]);

        $query = $request->input('q', '');
        $limit = $request->input('limit', 8);
        $currentUserId = $request->user()->id;

        $usersQuery = User::query()
            ->where('id', '!=', $currentUserId)
            ->where('email_verified_at', '!=', null);

        // If query provided, filter by name or email
        if (! empty($query)) {
            $usersQuery->where(function (Builder $q) use ($query) {
                $q->where('name', 'ILIKE', "%{$query}%")
                    ->orWhere('email', 'ILIKE', "%{$query}%");
            });
        }

        $users = $usersQuery
            ->select(['id', 'name', 'email', 'avatar'])
            ->orderBy('name')
            ->limit($limit)
            ->get();

        // Transform the data for frontend
        $transformedUsers = $users->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar ?? null,
            ];
        });

        return response()->json($transformedUsers);
    }

    /**
     * Get user profile information
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = User::where('id', $id)
            ->where('email_verified_at', '!=', null)
            ->select(['id', 'name', 'email', 'avatar'])
            ->first();

        if (! $user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $user->avatar ?? null,
        ]);
    }
}
