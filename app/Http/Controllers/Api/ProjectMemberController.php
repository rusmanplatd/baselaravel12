<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProjectMemberController extends Controller
{
    public function index($projectId)
    {
        $user = Auth::user();
        $project = Project::findOrFail($projectId);

        if (!$project->isVisible($user)) {
            return response()->json(['error' => 'Project not found'], 404);
        }

        $members = $project->members()->with(['user', 'addedBy'])->get();

        return response()->json($members);
    }

    public function store(Request $request, $projectId)
    {
        $user = Auth::user();
        $project = Project::findOrFail($projectId);

        if (!$project->canAdmin($user)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'required|in:admin,write,read',
            'permissions' => 'nullable|array',
        ]);

        $memberUser = User::findOrFail($validated['user_id']);

        if ($project->members()->where('user_id', $memberUser->id)->exists()) {
            return response()->json(['error' => 'User is already a member'], 400);
        }

        $member = $project->addMember(
            $memberUser,
            $validated['role'],
            $validated['permissions'] ?? [],
            $user
        );

        return response()->json($member->load(['user', 'addedBy']), 201);
    }

    public function show($projectId, $id)
    {
        $user = Auth::user();
        $project = Project::findOrFail($projectId);

        if (!$project->isVisible($user)) {
            return response()->json(['error' => 'Project not found'], 404);
        }

        $member = $project->members()->with(['user', 'addedBy'])->findOrFail($id);

        return response()->json($member);
    }

    public function update(Request $request, $projectId, $id)
    {
        $user = Auth::user();
        $project = Project::findOrFail($projectId);

        if (!$project->canAdmin($user)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $member = $project->members()->findOrFail($id);

        $validated = $request->validate([
            'role' => 'sometimes|required|in:admin,write,read',
            'permissions' => 'nullable|array',
        ]);

        if (isset($validated['role'])) {
            $member->updateRole($validated['role']);
        }

        if (isset($validated['permissions'])) {
            $member->update(['permissions' => $validated['permissions']]);
        }

        return response()->json($member->load(['user', 'addedBy']));
    }

    public function destroy($projectId, $id)
    {
        $user = Auth::user();
        $project = Project::findOrFail($projectId);

        if (!$project->canAdmin($user)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $member = $project->members()->findOrFail($id);

        if ($member->role === 'admin' && $project->members()->admins()->count() === 1) {
            return response()->json(['error' => 'Cannot remove the last admin'], 400);
        }

        $memberUser = $member->user;
        $member->delete();

        return response()->json(['message' => 'Member removed successfully']);
    }

    public function leave($projectId)
    {
        $user = Auth::user();
        $project = Project::findOrFail($projectId);

        $member = $project->members()->where('user_id', $user->id)->first();

        if (!$member) {
            return response()->json(['error' => 'You are not a member of this project'], 400);
        }

        if ($member->role === 'admin' && $project->members()->admins()->count() === 1) {
            return response()->json(['error' => 'Cannot leave project as the only admin'], 400);
        }

        $member->delete();

        return response()->json(['message' => 'Left project successfully']);
    }

    public function updateRole(Request $request, $projectId, $id)
    {
        $user = Auth::user();
        $project = Project::findOrFail($projectId);

        if (!$project->canAdmin($user)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'role' => 'required|in:admin,write,read',
        ]);

        $member = $project->members()->findOrFail($id);

        if ($member->role === 'admin' && $validated['role'] !== 'admin' && $project->members()->admins()->count() === 1) {
            return response()->json(['error' => 'Cannot demote the last admin'], 400);
        }

        $member->updateRole($validated['role']);

        return response()->json($member->load(['user', 'addedBy']));
    }

    public function availableUsers(Request $request, $projectId)
    {
        $user = Auth::user();
        $project = Project::findOrFail($projectId);

        if (!$project->canAdmin($user)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $existingMemberIds = $project->members()->pluck('user_id');

        $query = User::whereNotIn('id', $existingMemberIds);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('organization_id')) {
            $query->whereHas('organizations', function ($q) use ($request) {
                $q->where('organization_id', $request->input('organization_id'));
            });
        }

        $users = $query->select(['id', 'name', 'email', 'avatar'])
            ->limit(20)
            ->get();

        return response()->json($users);
    }
}