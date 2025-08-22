<?php

namespace App\Http\Controllers;

use App\Models\JobLevel;
use Illuminate\Http\Request;
use Inertia\Inertia;

class JobLevelController extends Controller
{
    public function index()
    {
        $jobLevels = JobLevel::query()
            ->withCount('jobPositions')
            ->orderBy('level_order')
            ->paginate(10);

        return Inertia::render('JobLevels/Index', [
            'jobLevels' => $jobLevels,
        ]);
    }

    public function create()
    {
        return Inertia::render('JobLevels/Create');
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

        JobLevel::create($validated);

        return redirect()->route('job-levels.index')
            ->with('success', 'Job level created successfully.');
    }

    public function show(JobLevel $jobLevel)
    {
        $jobLevel->load(['jobPositions.department.organization']);

        return Inertia::render('JobLevels/Show', [
            'jobLevel' => $jobLevel,
        ]);
    }

    public function edit(JobLevel $jobLevel)
    {
        return Inertia::render('JobLevels/Edit', [
            'jobLevel' => $jobLevel,
        ]);
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

        return redirect()->route('job-levels.index')
            ->with('success', 'Job level updated successfully.');
    }

    public function destroy(JobLevel $jobLevel)
    {
        $jobLevel->delete();

        return redirect()->route('job-levels.index')
            ->with('success', 'Job level deleted successfully.');
    }
}
