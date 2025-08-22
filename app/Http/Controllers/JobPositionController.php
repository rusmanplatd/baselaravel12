<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\JobLevel;
use App\Models\JobPosition;
use Illuminate\Http\Request;
use Inertia\Inertia;

class JobPositionController extends Controller
{
    public function index()
    {
        $jobPositions = JobPosition::query()
            ->with(['department.organization', 'jobLevel'])
            ->paginate(10);

        return Inertia::render('JobPositions/Index', [
            'jobPositions' => $jobPositions,
        ]);
    }

    public function create()
    {
        $departments = Department::with('organization')
            ->where('is_active', true)
            ->get();
        $jobLevels = JobLevel::where('is_active', true)
            ->orderBy('level_order')
            ->get();

        return Inertia::render('JobPositions/Create', [
            'departments' => $departments,
            'jobLevels' => $jobLevels,
        ]);
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

        JobPosition::create($validated);

        return redirect()->route('job-positions.index')
            ->with('success', 'Job position created successfully.');
    }

    public function show(JobPosition $jobPosition)
    {
        $jobPosition->load(['department.organization', 'jobLevel']);

        return Inertia::render('JobPositions/Show', [
            'jobPosition' => $jobPosition,
        ]);
    }

    public function edit(JobPosition $jobPosition)
    {
        $departments = Department::with('organization')
            ->where('is_active', true)
            ->get();
        $jobLevels = JobLevel::where('is_active', true)
            ->orderBy('level_order')
            ->get();

        return Inertia::render('JobPositions/Edit', [
            'jobPosition' => $jobPosition,
            'departments' => $departments,
            'jobLevels' => $jobLevels,
        ]);
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

        return redirect()->route('job-positions.index')
            ->with('success', 'Job position updated successfully.');
    }

    public function destroy(JobPosition $jobPosition)
    {
        $jobPosition->delete();

        return redirect()->route('job-positions.index')
            ->with('success', 'Job position deleted successfully.');
    }
}
