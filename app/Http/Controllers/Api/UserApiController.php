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
