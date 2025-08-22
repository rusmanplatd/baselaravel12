<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\JobLevelResource;
use App\Models\JobLevel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class JobLevelController extends Controller
{
    public function index(Request $request)
    {
        $query = JobLevel::query()->withCount('jobPositions')->orderBy('level_order');

        if ($request->has('include')) {
            $includes = explode(',', $request->get('include'));
            if (in_array('job_positions', $includes)) {
                $query->with(['jobPositions.department.organization']);
            }
        }

        $jobLevels = $query->paginate($request->get('per_page', 15));

        return JobLevelResource::collection($jobLevels);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'level_order' => 'required|integer|min:1',
            'min_salary' => 'nullable|numeric|min:0',
            'max_salary' => 'nullable|numeric|min:0|gte:min_salary',
            'is_active' => 'boolean',
        ]);

        $jobLevel = JobLevel::create($validated);

        return new JobLevelResource($jobLevel);
    }

    public function show(JobLevel $jobLevel, Request $request)
    {
        $query = JobLevel::query()->where('id', $jobLevel->id)->withCount('jobPositions');

        if ($request->has('include')) {
            $includes = explode(',', $request->get('include'));
            if (in_array('job_positions', $includes)) {
                $query->with(['jobPositions.department.organization']);
            }
        }

        $jobLevel = $query->first();

        return new JobLevelResource($jobLevel);
    }

    public function update(Request $request, JobLevel $jobLevel)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'level_order' => 'required|integer|min:1',
            'min_salary' => 'nullable|numeric|min:0',
            'max_salary' => 'nullable|numeric|min:0|gte:min_salary',
            'is_active' => 'boolean',
        ]);

        $jobLevel->update($validated);

        return new JobLevelResource($jobLevel);
    }

    public function destroy(JobLevel $jobLevel)
    {
        $jobLevel->delete();

        return response()->json(['message' => 'Job level deleted successfully'], Response::HTTP_OK);
    }
}
