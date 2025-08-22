<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\JobPositionResource;
use App\Models\JobPosition;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class JobPositionController extends Controller
{
    public function index(Request $request)
    {
        $query = JobPosition::query()->with(['department.organization', 'jobLevel']);

        if ($request->has('department_id')) {
            $query->where('department_id', $request->get('department_id'));
        }

        if ($request->has('job_level_id')) {
            $query->where('job_level_id', $request->get('job_level_id'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        $jobPositions = $query->paginate($request->get('per_page', 15));

        return JobPositionResource::collection($jobPositions);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'department_id' => 'required|exists:departments,id',
            'job_level_id' => 'required|exists:job_levels,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'requirements' => 'nullable|string',
            'responsibilities' => 'nullable|string',
            'openings' => 'required|integer|min:1',
            'employment_type' => 'required|in:full_time,part_time,contract,internship',
            'status' => 'required|in:open,closed,on_hold',
            'is_active' => 'boolean',
        ]);

        $jobPosition = JobPosition::create($validated);

        return new JobPositionResource($jobPosition->load(['department.organization', 'jobLevel']));
    }

    public function show(JobPosition $jobPosition)
    {
        $jobPosition->load(['department.organization', 'jobLevel']);

        return new JobPositionResource($jobPosition);
    }

    public function update(Request $request, JobPosition $jobPosition)
    {
        $validated = $request->validate([
            'department_id' => 'required|exists:departments,id',
            'job_level_id' => 'required|exists:job_levels,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'requirements' => 'nullable|string',
            'responsibilities' => 'nullable|string',
            'openings' => 'required|integer|min:1',
            'employment_type' => 'required|in:full_time,part_time,contract,internship',
            'status' => 'required|in:open,closed,on_hold',
            'is_active' => 'boolean',
        ]);

        $jobPosition->update($validated);

        return new JobPositionResource($jobPosition->load(['department.organization', 'jobLevel']));
    }

    public function destroy(JobPosition $jobPosition)
    {
        $jobPosition->delete();

        return response()->json(['message' => 'Job position deleted successfully'], Response::HTTP_OK);
    }
}
