<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectItem;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProjectItemController extends Controller
{
    public function index(Request $request, $projectId)
    {
        $user = Auth::user();
        $project = Project::findOrFail($projectId);

        if (!$project->isVisible($user)) {
            return response()->json(['error' => 'Project not found'], 404);
        }

        $query = $project->items()->with(['creator', 'assignees']);

        if ($request->filled('status')) {
            $query->byStatus($request->input('status'));
        }

        if ($request->filled('type')) {
            $query->byType($request->input('type'));
        }

        if ($request->filled('assignee_id')) {
            $query->whereHas('assignees', function ($q) use ($request) {
                $q->where('user_id', $request->input('assignee_id'));
            });
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->boolean('archived')) {
            $query->archived();
        } else {
            $query->active();
        }

        $items = $query->orderBy('sort_order')
            ->paginate($request->input('per_page', 25));

        return response()->json($items);
    }

    public function store(Request $request, $projectId)
    {
        $user = Auth::user();
        $project = Project::findOrFail($projectId);

        if (!$project->canEdit($user)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:issue,pull_request,draft_issue',
            'field_values' => 'nullable|array',
            'assignee_ids' => 'nullable|array',
            'assignee_ids.*' => 'exists:users,id',
        ]);

        $item = DB::transaction(function () use ($validated, $project, $user) {
            $maxSortOrder = $project->items()->max('sort_order') ?? 0;

            $item = $project->items()->create([
                'title' => $validated['title'],
                'description' => $validated['description'],
                'type' => $validated['type'],
                'field_values' => $validated['field_values'] ?? [],
                'sort_order' => $maxSortOrder + 1,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            if (!empty($validated['assignee_ids'])) {
                $item->assignees()->sync($validated['assignee_ids']);
                $item->updateFieldValue('assignees', $validated['assignee_ids']);
            }

            return $item;
        });

        return response()->json($item->load(['creator', 'assignees']), 201);
    }

    public function show($projectId, $id)
    {
        $user = Auth::user();
        $project = Project::findOrFail($projectId);

        if (!$project->isVisible($user)) {
            return response()->json(['error' => 'Project not found'], 404);
        }

        $item = $project->items()->with(['creator', 'assignees', 'updatedBy'])
            ->findOrFail($id);

        return response()->json($item);
    }

    public function update(Request $request, $projectId, $id)
    {
        $user = Auth::user();
        $project = Project::findOrFail($projectId);

        if (!$project->canEdit($user)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $item = $project->items()->findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'sometimes|required|in:todo,in_progress,done,archived',
            'field_values' => 'nullable|array',
            'assignee_ids' => 'nullable|array',
            'assignee_ids.*' => 'exists:users,id',
        ]);

        DB::transaction(function () use ($validated, $item, $user) {
            $oldStatus = $item->status;
            
            $updateData = array_intersect_key($validated, array_flip(['title', 'description', 'status']));
            $updateData['updated_by'] = $user->id;

            if (isset($validated['status']) && $validated['status'] === 'done' && $oldStatus !== 'done') {
                $updateData['completed_at'] = now();
            } elseif (isset($validated['status']) && $validated['status'] !== 'done' && $oldStatus === 'done') {
                $updateData['completed_at'] = null;
            }

            $item->update($updateData);

            if (isset($validated['field_values'])) {
                foreach ($validated['field_values'] as $fieldName => $value) {
                    $item->updateFieldValue($fieldName, $value);
                }
            }

            if (isset($validated['assignee_ids'])) {
                $item->assignees()->sync($validated['assignee_ids']);
                $item->updateFieldValue('assignees', $validated['assignee_ids']);
            }
        });

        return response()->json($item->load(['creator', 'assignees', 'updatedBy']));
    }

    public function destroy($projectId, $id)
    {
        $user = Auth::user();
        $project = Project::findOrFail($projectId);

        if (!$project->canEdit($user)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $item = $project->items()->findOrFail($id);
        $item->delete();

        return response()->json(['message' => 'Project item deleted successfully']);
    }

    public function assignUser(Request $request, $projectId, $id)
    {
        $user = Auth::user();
        $project = Project::findOrFail($projectId);

        if (!$project->canEdit($user)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $item = $project->items()->findOrFail($id);
        $assigneeUser = User::findOrFail($validated['user_id']);

        $item->assignTo($assigneeUser);

        return response()->json($item->load(['assignees']));
    }

    public function unassignUser(Request $request, $projectId, $id)
    {
        $user = Auth::user();
        $project = Project::findOrFail($projectId);

        if (!$project->canEdit($user)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $item = $project->items()->findOrFail($id);
        $assigneeUser = User::findOrFail($validated['user_id']);

        $item->unassignFrom($assigneeUser);

        return response()->json($item->load(['assignees']));
    }

    public function complete($projectId, $id)
    {
        $user = Auth::user();
        $project = Project::findOrFail($projectId);

        if (!$project->canEdit($user)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $item = $project->items()->findOrFail($id);
        $item->complete();

        return response()->json($item);
    }

    public function reopen($projectId, $id)
    {
        $user = Auth::user();
        $project = Project::findOrFail($projectId);

        if (!$project->canEdit($user)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $item = $project->items()->findOrFail($id);
        $item->reopen();

        return response()->json($item);
    }

    public function archive($projectId, $id)
    {
        $user = Auth::user();
        $project = Project::findOrFail($projectId);

        if (!$project->canEdit($user)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $item = $project->items()->findOrFail($id);
        $item->archive();

        return response()->json($item);
    }

    public function unarchive($projectId, $id)
    {
        $user = Auth::user();
        $project = Project::findOrFail($projectId);

        if (!$project->canEdit($user)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $item = $project->items()->findOrFail($id);
        $item->unarchive();

        return response()->json($item);
    }

    public function updateOrder(Request $request, $projectId)
    {
        $user = Auth::user();
        $project = Project::findOrFail($projectId);

        if (!$project->canEdit($user)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|exists:project_items,id',
            'items.*.sort_order' => 'required|integer|min:0',
        ]);

        DB::transaction(function () use ($validated, $project) {
            foreach ($validated['items'] as $itemData) {
                $project->items()
                    ->where('id', $itemData['id'])
                    ->update(['sort_order' => $itemData['sort_order']]);
            }
        });

        return response()->json(['message' => 'Item order updated successfully']);
    }

    public function updateStatus(Request $request, $projectId, $id)
    {
        $user = Auth::user();
        $project = Project::findOrFail($projectId);

        if (!$project->canEdit($user)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $item = $project->items()->findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:todo,in_progress,done,archived',
        ]);

        $oldStatus = $item->status;
        $newStatus = $validated['status'];

        $updateData = [
            'status' => $newStatus,
            'updated_by' => $user->id,
        ];

        if ($newStatus === 'done' && $oldStatus !== 'done') {
            $updateData['completed_at'] = now();
        } elseif ($newStatus !== 'done' && $oldStatus === 'done') {
            $updateData['completed_at'] = null;
        }

        $item->update($updateData);

        return response()->json($item->load(['creator', 'assignees', 'updatedBy']));
    }
}