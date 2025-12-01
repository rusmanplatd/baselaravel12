<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectView;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProjectViewController extends Controller
{
    public function index($projectId)
    {
        $user = Auth::user();
        $project = Project::findOrFail($projectId);

        if (!$project->isVisible($user)) {
            return response()->json(['error' => 'Project not found'], 404);
        }

        $views = $project->views()->forUser($user)->get();

        return response()->json($views);
    }

    public function store(Request $request, $projectId)
    {
        $user = Auth::user();
        $project = Project::findOrFail($projectId);

        if (!$project->canEdit($user)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'layout' => 'required|in:table,board,timeline,roadmap',
            'filters' => 'nullable|array',
            'sort' => 'nullable|array',
            'group_by' => 'nullable|array',
            'visible_fields' => 'nullable|array',
            'settings' => 'nullable|array',
            'is_public' => 'boolean',
        ]);

        $view = $project->views()->create([
            'name' => $validated['name'],
            'layout' => $validated['layout'],
            'filters' => $validated['filters'],
            'sort' => $validated['sort'],
            'group_by' => $validated['group_by'],
            'visible_fields' => $validated['visible_fields'],
            'settings' => $validated['settings'],
            'is_public' => $validated['is_public'] ?? false,
            'created_by' => $user->id,
        ]);

        return response()->json($view, 201);
    }

    public function show($projectId, $id)
    {
        $user = Auth::user();
        $project = Project::findOrFail($projectId);

        if (!$project->isVisible($user)) {
            return response()->json(['error' => 'Project not found'], 404);
        }

        $view = $project->views()->findOrFail($id);

        if (!$view->is_public && $view->created_by !== $user->id && !$project->canAdmin($user)) {
            return response()->json(['error' => 'View not found'], 404);
        }

        return response()->json($view);
    }

    public function update(Request $request, $projectId, $id)
    {
        $user = Auth::user();
        $project = Project::findOrFail($projectId);
        $view = $project->views()->findOrFail($id);

        if (!$view->canEdit($user)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'layout' => 'sometimes|required|in:table,board,timeline,roadmap',
            'filters' => 'nullable|array',
            'sort' => 'nullable|array',
            'group_by' => 'nullable|array',
            'visible_fields' => 'nullable|array',
            'settings' => 'nullable|array',
            'is_public' => 'boolean',
        ]);

        $validated['updated_by'] = $user->id;
        $view->update($validated);

        return response()->json($view);
    }

    public function destroy($projectId, $id)
    {
        $user = Auth::user();
        $project = Project::findOrFail($projectId);
        $view = $project->views()->findOrFail($id);

        if (!$view->canEdit($user)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($view->is_default) {
            return response()->json(['error' => 'Cannot delete default view'], 400);
        }

        $view->delete();

        return response()->json(['message' => 'View deleted successfully']);
    }

    public function makeDefault($projectId, $id)
    {
        $user = Auth::user();
        $project = Project::findOrFail($projectId);

        if (!$project->canAdmin($user)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $view = $project->views()->findOrFail($id);
        $view->makeDefault();

        return response()->json($view);
    }

    public function items($projectId, $id)
    {
        $user = Auth::user();
        $project = Project::findOrFail($projectId);

        if (!$project->isVisible($user)) {
            return response()->json(['error' => 'Project not found'], 404);
        }

        $view = $project->views()->findOrFail($id);

        if (!$view->is_public && $view->created_by !== $user->id && !$project->canAdmin($user)) {
            return response()->json(['error' => 'View not found'], 404);
        }

        $items = $view->getFilteredItems()->with(['creator', 'assignees'])->get();

        return response()->json($items);
    }
}