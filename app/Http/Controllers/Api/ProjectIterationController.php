<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectIteration;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class ProjectIterationController extends Controller
{
    public function index(Request $request, Project $project): JsonResponse
    {
        $iterations = $project->iterations()
            ->with(['creator', 'items' => function($query) {
                $query->select('id', 'iteration_id', 'status', 'estimate', 'progress');
            }])
            ->orderBy('start_date', 'desc')
            ->get();

        // Add completion stats to each iteration
        $iterations->each(function($iteration) {
            $iteration->completion_stats = $iteration->getCompletionStats();
            $iteration->time_stats = [
                'duration_days' => $iteration->getDurationInDays(),
                'remaining_days' => $iteration->getRemainingDays(),
                'progress_percentage' => $iteration->getProgressPercentage(),
            ];
        });

        return response()->json([
            'data' => $iterations,
            'meta' => [
                'total' => $iterations->count(),
                'current_iteration' => $project->iterations()->current()->first(),
            ]
        ]);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'duration_weeks' => 'nullable|integer|min:1|max:52',
            'goals' => 'nullable|array',
            'goals.*' => 'string',
        ]);

        $validated['project_id'] = $project->id;
        $validated['created_by'] = auth()->id();
        
        // Calculate duration_weeks if not provided
        if (!isset($validated['duration_weeks'])) {
            $start = new \DateTime($validated['start_date']);
            $end = new \DateTime($validated['end_date']);
            $validated['duration_weeks'] = ceil($start->diff($end)->days / 7);
        }

        $iteration = ProjectIteration::create($validated);
        $iteration->load(['creator', 'items']);

        return response()->json([
            'data' => $iteration,
            'message' => 'Iteration created successfully'
        ], 201);
    }

    public function show(Project $project, ProjectIteration $iteration): JsonResponse
    {
        $iteration->load([
            'creator',
            'items' => function($query) {
                $query->with(['assignees', 'creator'])->orderBy('sort_order');
            }
        ]);

        $iteration->completion_stats = $iteration->getCompletionStats();
        $iteration->time_stats = [
            'duration_days' => $iteration->getDurationInDays(),
            'remaining_days' => $iteration->getRemainingDays(),
            'progress_percentage' => $iteration->getProgressPercentage(),
        ];

        return response()->json(['data' => $iteration]);
    }

    public function update(Request $request, Project $project, ProjectIteration $iteration): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|required|date|after:start_date',
            'status' => ['sometimes', 'required', Rule::in(['planned', 'active', 'completed', 'cancelled'])],
            'duration_weeks' => 'nullable|integer|min:1|max:52',
            'goals' => 'nullable|array',
            'goals.*' => 'string',
        ]);

        $iteration->update($validated);
        $iteration->load(['creator', 'items']);

        return response()->json([
            'data' => $iteration,
            'message' => 'Iteration updated successfully'
        ]);
    }

    public function destroy(Project $project, ProjectIteration $iteration): JsonResponse
    {
        // Check if iteration has items
        if ($iteration->items()->count() > 0) {
            return response()->json([
                'error' => 'Cannot delete iteration with items',
                'message' => 'Move all items out of this iteration before deleting it'
            ], 422);
        }

        $iteration->delete();

        return response()->json(['message' => 'Iteration deleted successfully']);
    }

    public function start(Project $project, ProjectIteration $iteration): JsonResponse
    {
        $iteration->start();
        
        return response()->json([
            'data' => $iteration->fresh(),
            'message' => 'Iteration started successfully'
        ]);
    }

    public function complete(Project $project, ProjectIteration $iteration): JsonResponse
    {
        $iteration->complete();
        
        return response()->json([
            'data' => $iteration->fresh(),
            'message' => 'Iteration completed successfully'
        ]);
    }

    public function cancel(Project $project, ProjectIteration $iteration): JsonResponse
    {
        $iteration->cancel();
        
        return response()->json([
            'data' => $iteration->fresh(),
            'message' => 'Iteration cancelled successfully'
        ]);
    }

    public function addItems(Request $request, Project $project, ProjectIteration $iteration): JsonResponse
    {
        $validated = $request->validate([
            'item_ids' => 'required|array',
            'item_ids.*' => 'required|string|exists:project_items,id',
        ]);

        $items = $project->items()->whereIn('id', $validated['item_ids'])->get();
        
        foreach ($items as $item) {
            $item->assignToIteration($iteration);
        }

        return response()->json([
            'message' => 'Items added to iteration successfully',
            'added_count' => $items->count(),
        ]);
    }

    public function removeItems(Request $request, Project $project, ProjectIteration $iteration): JsonResponse
    {
        $validated = $request->validate([
            'item_ids' => 'required|array',
            'item_ids.*' => 'required|string|exists:project_items,id',
        ]);

        $items = $iteration->items()->whereIn('id', $validated['item_ids'])->get();
        
        foreach ($items as $item) {
            $item->removeFromIteration();
        }

        return response()->json([
            'message' => 'Items removed from iteration successfully',
            'removed_count' => $items->count(),
        ]);
    }
}