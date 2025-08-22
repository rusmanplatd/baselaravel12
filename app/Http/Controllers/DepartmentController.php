<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Organization;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DepartmentController extends Controller
{
    public function index()
    {
        $departments = Department::query()
            ->with(['organization', 'parentDepartment'])
            ->withCount(['jobPositions', 'childDepartments'])
            ->paginate(10);

        return Inertia::render('Departments/Index', [
            'departments' => $departments,
        ]);
    }

    public function create()
    {
        $organizations = Organization::where('is_active', true)->get();
        $departments = Department::where('is_active', true)->get();

        return Inertia::render('Departments/Create', [
            'organizations' => $organizations,
            'departments' => $departments,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'organization_id' => 'required|exists:organizations,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'parent_department_id' => 'nullable|exists:departments,id',
            'is_active' => 'boolean',
        ]);

        Department::create($validated);

        return redirect()->route('departments.index')
            ->with('success', 'Department created successfully.');
    }

    public function show(Department $department)
    {
        $department->load([
            'organization',
            'parentDepartment',
            'childDepartments.jobPositions.jobLevel',
            'jobPositions.jobLevel'
        ]);

        return Inertia::render('Departments/Show', [
            'department' => $department,
        ]);
    }

    public function edit(Department $department)
    {
        $organizations = Organization::where('is_active', true)->get();
        $departments = Department::where('is_active', true)
            ->where('id', '!=', $department->id)
            ->get();

        return Inertia::render('Departments/Edit', [
            'department' => $department,
            'organizations' => $organizations,
            'departments' => $departments,
        ]);
    }

    public function update(Request $request, Department $department)
    {
        $validated = $request->validate([
            'organization_id' => 'required|exists:organizations,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'parent_department_id' => 'nullable|exists:departments,id',
            'is_active' => 'boolean',
        ]);

        $department->update($validated);

        return redirect()->route('departments.index')
            ->with('success', 'Department updated successfully.');
    }

    public function destroy(Department $department)
    {
        $department->delete();

        return redirect()->route('departments.index')
            ->with('success', 'Department deleted successfully.');
    }
}
