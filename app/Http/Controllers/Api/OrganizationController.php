<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrganizationResource;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class OrganizationController extends Controller
{
    public function index(Request $request)
    {
        $query = Organization::query()->withCount('departments');

        if ($request->has('include')) {
            $includes = explode(',', $request->get('include'));
            if (in_array('departments', $includes)) {
                $query->with(['departments' => function ($query) {
                    $query->withCount('jobPositions');
                }]);
            }
        }

        $organizations = $query->paginate($request->get('per_page', 15));

        return OrganizationResource::collection($organizations);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:255',
            'is_active' => 'boolean',
        ]);

        $organization = Organization::create($validated);

        return new OrganizationResource($organization);
    }

    public function show(Organization $organization, Request $request)
    {
        $query = Organization::query()->where('id', $organization->id);

        if ($request->has('include')) {
            $includes = explode(',', $request->get('include'));
            if (in_array('departments', $includes)) {
                $query->with(['departments' => function ($query) {
                    $query->with(['jobPositions.jobLevel'])->withCount(['jobPositions', 'childDepartments']);
                }]);
            }
        }

        $organization = $query->first();

        return new OrganizationResource($organization);
    }

    public function update(Request $request, Organization $organization)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:255',
            'is_active' => 'boolean',
        ]);

        $organization->update($validated);

        return new OrganizationResource($organization);
    }

    public function destroy(Organization $organization)
    {
        $organization->delete();

        return response()->json(['message' => 'Organization deleted successfully'], Response::HTTP_OK);
    }
}
