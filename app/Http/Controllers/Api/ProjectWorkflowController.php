<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProjectWorkflowController extends Controller
{
    public function index($projectId)
    {
        $user = Auth::user();
        $project = Project::findOrFail($projectId);

        if (!$project->isVisible($user)) {
            return response()->json(['error' => 'Project not found'], 404);
        }

        $workflows = $project->workflows()->orderBy('sort_order')->get();

        return response()->json($workflows);
    }

    public function store(Request $request, $projectId)
    {
        $user = Auth::user();
        $project = Project::findOrFail($projectId);

        if (!$project->canAdmin($user)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'triggers' => 'required|array',
            'triggers.*.event' => 'required|string',
            'triggers.*.conditions' => 'nullable|array',
            'actions' => 'required|array',
            'actions.*.type' => 'required|string',
            'actions.*.config' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        $maxSortOrder = $project->workflows()->max('sort_order') ?? 0;

        $workflow = $project->workflows()->create([
            'name' => $validated['name'],
            'description' => $validated['description'],
            'triggers' => $validated['triggers'],
            'actions' => $validated['actions'],
            'is_active' => $validated['is_active'] ?? true,
            'sort_order' => $maxSortOrder + 1,
            'created_by' => $user->id,
        ]);

        return response()->json($workflow, 201);
    }

    public function show($projectId, $id)
    {
        $user = Auth::user();
        $project = Project::findOrFail($projectId);

        if (!$project->isVisible($user)) {
            return response()->json(['error' => 'Project not found'], 404);
        }

        $workflow = $project->workflows()->findOrFail($id);

        return response()->json($workflow);
    }

    public function update(Request $request, $projectId, $id)
    {
        $user = Auth::user();
        $project = Project::findOrFail($projectId);

        if (!$project->canAdmin($user)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $workflow = $project->workflows()->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'triggers' => 'sometimes|required|array',
            'triggers.*.event' => 'required|string',
            'triggers.*.conditions' => 'nullable|array',
            'actions' => 'sometimes|required|array',
            'actions.*.type' => 'required|string',
            'actions.*.config' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        $workflow->update($validated);

        return response()->json($workflow);
    }

    public function destroy($projectId, $id)
    {
        $user = Auth::user();
        $project = Project::findOrFail($projectId);

        if (!$project->canAdmin($user)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $workflow = $project->workflows()->findOrFail($id);
        $workflow->delete();

        return response()->json(['message' => 'Workflow deleted successfully']);
    }

    public function toggle($projectId, $id)
    {
        $user = Auth::user();
        $project = Project::findOrFail($projectId);

        if (!$project->canAdmin($user)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $workflow = $project->workflows()->findOrFail($id);
        $workflow->update(['is_active' => !$workflow->is_active]);

        return response()->json($workflow);
    }

    public function updateOrder(Request $request, $projectId)
    {
        $user = Auth::user();
        $project = Project::findOrFail($projectId);

        if (!$project->canAdmin($user)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'workflows' => 'required|array',
            'workflows.*.id' => 'required|exists:project_workflows,id',
            'workflows.*.sort_order' => 'required|integer|min:0',
        ]);

        DB::transaction(function () use ($validated, $project) {
            foreach ($validated['workflows'] as $workflowData) {
                $project->workflows()
                    ->where('id', $workflowData['id'])
                    ->update(['sort_order' => $workflowData['sort_order']]);
            }
        });

        return response()->json(['message' => 'Workflow order updated successfully']);
    }

    public function duplicate($projectId, $id)
    {
        $user = Auth::user();
        $project = Project::findOrFail($projectId);

        if (!$project->canAdmin($user)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $workflow = $project->workflows()->findOrFail($id);
        $maxSortOrder = $project->workflows()->max('sort_order') ?? 0;

        $duplicatedWorkflow = $project->workflows()->create([
            'name' => $workflow->name . ' (Copy)',
            'description' => $workflow->description,
            'triggers' => $workflow->triggers,
            'actions' => $workflow->actions,
            'is_active' => false, // Start as inactive
            'sort_order' => $maxSortOrder + 1,
            'created_by' => $user->id,
        ]);

        return response()->json($duplicatedWorkflow, 201);
    }

    public function getAvailableEvents($projectId)
    {
        $user = Auth::user();
        $project = Project::findOrFail($projectId);

        if (!$project->isVisible($user)) {
            return response()->json(['error' => 'Project not found'], 404);
        }

        $events = [
            [
                'id' => 'item.created',
                'name' => 'Item Created',
                'description' => 'Triggered when a new item is created',
                'category' => 'item',
            ],
            [
                'id' => 'item.updated',
                'name' => 'Item Updated',
                'description' => 'Triggered when an item is updated',
                'category' => 'item',
            ],
            [
                'id' => 'item.status_changed',
                'name' => 'Item Status Changed',
                'description' => 'Triggered when an item status changes',
                'category' => 'item',
            ],
            [
                'id' => 'item.assigned',
                'name' => 'Item Assigned',
                'description' => 'Triggered when an item is assigned to someone',
                'category' => 'item',
            ],
            [
                'id' => 'item.completed',
                'name' => 'Item Completed',
                'description' => 'Triggered when an item is marked as completed',
                'category' => 'item',
            ],
            [
                'id' => 'member.added',
                'name' => 'Member Added',
                'description' => 'Triggered when a new member joins the project',
                'category' => 'project',
            ],
            [
                'id' => 'member.removed',
                'name' => 'Member Removed',
                'description' => 'Triggered when a member leaves the project',
                'category' => 'project',
            ],
        ];

        return response()->json($events);
    }

    public function getAvailableActions($projectId)
    {
        $user = Auth::user();
        $project = Project::findOrFail($projectId);

        if (!$project->isVisible($user)) {
            return response()->json(['error' => 'Project not found'], 404);
        }

        $actions = [
            [
                'id' => 'assign_user',
                'name' => 'Assign User',
                'description' => 'Assign the item to a specific user',
                'category' => 'assignment',
                'config_schema' => [
                    'user_id' => ['type' => 'user_select', 'required' => true]
                ]
            ],
            [
                'id' => 'change_status',
                'name' => 'Change Status',
                'description' => 'Change the item status',
                'category' => 'status',
                'config_schema' => [
                    'status' => ['type' => 'status_select', 'required' => true]
                ]
            ],
            [
                'id' => 'add_label',
                'name' => 'Add Label',
                'description' => 'Add a label to the item',
                'category' => 'labeling',
                'config_schema' => [
                    'label' => ['type' => 'text', 'required' => true]
                ]
            ],
            [
                'id' => 'send_notification',
                'name' => 'Send Notification',
                'description' => 'Send a notification to specified users',
                'category' => 'notification',
                'config_schema' => [
                    'recipients' => ['type' => 'user_multi_select', 'required' => true],
                    'message' => ['type' => 'text', 'required' => true]
                ]
            ],
            [
                'id' => 'update_field',
                'name' => 'Update Field',
                'description' => 'Update a custom field value',
                'category' => 'fields',
                'config_schema' => [
                    'field_id' => ['type' => 'field_select', 'required' => true],
                    'value' => ['type' => 'dynamic', 'required' => true]
                ]
            ],
        ];

        return response()->json($actions);
    }
}