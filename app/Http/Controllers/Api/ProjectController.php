<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Organization;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $this->authorize('viewAny', Project::class);
        
        $query = Project::with(['organization', 'creator', 'members.user'])
            ->where(function ($q) use ($user) {
                $q->whereHas('members', function ($memberQuery) use ($user) {
                    $memberQuery->where('user_id', $user->id);
                });
            });

        if ($request->filled('organization_id')) {
            $organizationId = $request->input('organization_id');
            $organization = Organization::findOrFail($organizationId);
            
            $query->where('organization_id', $organizationId);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $projects = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return response()->json($projects);
    }

    public function store(StoreProjectRequest $request)
    {
        $validated = $request->validated();

        $user = Auth::user();
        $organization = Organization::findOrFail($validated['organization_id']);

        $project = DB::transaction(function () use ($validated, $user, $organization) {
            $project = Project::create([
                'title' => $validated['title'],
                'description' => $validated['description'],
                'visibility' => $validated['visibility'],
                'organization_id' => $organization->id,
                'created_by' => $user->id,
                'updated_by' => $user->id,
                'settings' => $validated['settings'] ?? [],
            ]);

            $project->addMember($user, 'admin', [], $user);

            $project->initializeDefaultFields();
            $project->initializeDefaultView();

            return $project;
        });

        return response()->json($project->load(['organization', 'creator', 'members.user']), 201);
    }

    public function show($id)
    {
        $project = Project::with([
            'organization',
            'creator',
            'members.user',
            'fields',
            'views',
            'workflows',
            'items' => function ($query) {
                $query->active()->orderBy('sort_order');
            }
        ])->findOrFail($id);

        $this->authorize('view', $project);

        return response()->json($project);
    }

    public function update(UpdateProjectRequest $request, $id)
    {
        $project = Project::findOrFail($id);
        $validated = $request->validated();

        $validated['updated_by'] = Auth::id();

        $project->update($validated);

        return response()->json($project->load(['organization', 'creator', 'members.user']));
    }

    public function destroy($id)
    {
        $project = Project::findOrFail($id);
        $this->authorize('delete', $project);

        $project->delete();

        return response()->json(['message' => 'Project deleted successfully']);
    }

    public function close($id)
    {
        $user = Auth::user();
        $project = Project::findOrFail($id);

        if (!$project->canAdmin($user)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $project->close();

        return response()->json($project);
    }

    public function reopen($id)
    {
        $user = Auth::user();
        $project = Project::findOrFail($id);

        if (!$project->canAdmin($user)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $project->reopen();

        return response()->json($project);
    }

    public function fields($id)
    {
        $user = Auth::user();
        $project = Project::findOrFail($id);

        if (!$project->isVisible($user)) {
            return response()->json(['error' => 'Project not found'], 404);
        }

        return response()->json($project->fields);
    }

    public function addField(Request $request, $id)
    {
        $user = Auth::user();
        $project = Project::findOrFail($id);

        if (!$project->canAdmin($user)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:text,number,date,single_select,multi_select,assignees,repository,milestone,iteration',
            'options' => 'nullable|array',
            'settings' => 'nullable|array',
            'is_required' => 'boolean',
        ]);

        $validated['project_id'] = $project->id;
        $validated['sort_order'] = $project->fields()->max('sort_order') + 1;

        $field = $project->fields()->create($validated);

        return response()->json($field, 201);
    }

    public function stats($id)
    {
        $user = Auth::user();
        $project = Project::findOrFail($id);

        if (!$project->isVisible($user)) {
            return response()->json(['error' => 'Project not found'], 404);
        }

        $stats = [
            'total_items' => $project->items()->active()->count(),
            'todo_items' => $project->items()->active()->byStatus('todo')->count(),
            'in_progress_items' => $project->items()->active()->byStatus('in_progress')->count(),
            'done_items' => $project->items()->active()->byStatus('done')->count(),
            'total_members' => $project->members()->count(),
            'admin_members' => $project->members()->admins()->count(),
            'total_views' => $project->views()->count(),
            'active_workflows' => $project->workflows()->active()->count(),
        ];

        return response()->json($stats);
    }
}