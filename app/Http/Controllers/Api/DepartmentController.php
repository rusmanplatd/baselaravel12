<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DepartmentResource;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DepartmentController extends Controller
{
    public function index(Request $request)
    {
        $query = Department::query()
            ->with(['organization', 'parentDepartment'])
            ->withCount(['jobPositions', 'childDepartments']);

        if ($request->has('organization_id')) {
            $query->where('organization_id', $request->get('organization_id'));
        }

        if ($request->has('include')) {
            $includes = explode(',', $request->get('include'));
            if (in_array('job_positions', $includes)) {
                $query->with(['jobPositions.jobLevel']);
            }
            if (in_array('child_departments', $includes)) {
                $query->with(['childDepartments']);
            }
        }

        $departments = $query->paginate($request->get('per_page', 15));

        return DepartmentResource::collection($departments);
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

        $department = Department::create($validated);

        return new DepartmentResource($department->load('organization'));
    }

    public function show(Department $department, Request $request)
    {
        $query = Department::query()->where('id', $department->id)
            ->with(['organization', 'parentDepartment'])
            ->withCount(['jobPositions', 'childDepartments']);

        if ($request->has('include')) {
            $includes = explode(',', $request->get('include'));
            if (in_array('job_positions', $includes)) {
                $query->with(['jobPositions.jobLevel']);
            }
            if (in_array('child_departments', $includes)) {
                $query->with(['childDepartments']);
            }
        }

        $department = $query->first();

        return new DepartmentResource($department);
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

        return new DepartmentResource($department->load('organization'));
    }

    public function destroy(Department $department)
    {
        $department->delete();

        return response()->json(['message' => 'Department deleted successfully'], Response::HTTP_OK);
    }
}
