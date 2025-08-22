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
        $query = Organization::query()
            ->withCount(['departments', 'organizationUnits', 'childOrganizations']);

        if ($request->has('organization_type')) {
            $query->where('organization_type', $request->organization_type);
        }

        if ($request->has('parent_organization_id')) {
            $query->where('parent_organization_id', $request->parent_organization_id);
        }

        if ($request->has('hierarchy_root') && $request->hierarchy_root) {
            $query->whereNull('parent_organization_id');
        }

        if ($request->has('include')) {
            $includes = explode(',', $request->get('include'));

            if (in_array('departments', $includes)) {
                $query->with(['departments' => function ($query) {
                    $query->withCount('jobPositions');
                }]);
            }

            if (in_array('parent', $includes)) {
                $query->with('parentOrganization');
            }

            if (in_array('children', $includes)) {
                $query->with('childOrganizations');
            }

            if (in_array('units', $includes)) {
                $query->with(['organizationUnits' => function ($query) {
                    $query->with('positions')->orderBy('sort_order');
                }]);
            }
        }

        $organizations = $query->orderBy('level')->orderBy('name')
            ->paginate($request->get('per_page', 15));

        return OrganizationResource::collection($organizations);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'organization_code' => 'nullable|string|unique:organizations',
            'name' => 'required|string|max:255',
            'organization_type' => 'required|in:holding_company,subsidiary,division,branch,department,unit',
            'parent_organization_id' => 'nullable|exists:organizations,id',
            'description' => 'nullable|string',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:255',
            'registration_number' => 'nullable|string|max:100',
            'tax_number' => 'nullable|string|max:100',
            'governance_structure' => 'nullable|array',
            'authorized_capital' => 'nullable|numeric|min:0',
            'paid_capital' => 'nullable|numeric|min:0',
            'establishment_date' => 'nullable|date',
            'legal_status' => 'nullable|string|max:100',
            'business_activities' => 'nullable|string',
            'contact_persons' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        $organization = Organization::create($validated);

        if ($organization->parent_organization_id) {
            $organization->updatePath();
        }

        return new OrganizationResource($organization->load(['parentOrganization', 'childOrganizations']));
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
            'organization_code' => 'nullable|string|unique:organizations,organization_code,'.$organization->id,
            'name' => 'required|string|max:255',
            'organization_type' => 'required|in:holding_company,subsidiary,division,branch,department,unit',
            'parent_organization_id' => 'nullable|exists:organizations,id',
            'description' => 'nullable|string',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:255',
            'registration_number' => 'nullable|string|max:100',
            'tax_number' => 'nullable|string|max:100',
            'governance_structure' => 'nullable|array',
            'authorized_capital' => 'nullable|numeric|min:0',
            'paid_capital' => 'nullable|numeric|min:0',
            'establishment_date' => 'nullable|date',
            'legal_status' => 'nullable|string|max:100',
            'business_activities' => 'nullable|string',
            'contact_persons' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        if (isset($validated['parent_organization_id']) && $validated['parent_organization_id'] == $organization->id) {
            return response()->json([
                'message' => 'Organization cannot be its own parent',
            ], 400);
        }

        $oldParentId = $organization->parent_organization_id;
        $organization->update($validated);

        if ($oldParentId !== $organization->parent_organization_id) {
            $organization->updatePath();
        }

        return new OrganizationResource($organization->load(['parentOrganization', 'childOrganizations']));
    }

    public function destroy(Organization $organization)
    {
        if ($organization->childOrganizations()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete organization with child organizations. Please reassign or delete child organizations first.',
            ], 400);
        }

        if ($organization->organizationUnits()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete organization with organizational units. Please delete units first.',
            ], 400);
        }

        $organization->delete();

        return response()->json(['message' => 'Organization deleted successfully'], Response::HTTP_OK);
    }

    public function getHierarchy(Request $request)
    {
        $rootOrganizations = Organization::whereNull('parent_organization_id')
            ->with(['childOrganizations' => function ($query) {
                $this->loadOrganizationHierarchy($query);
            }])
            ->orderBy('name')
            ->get();

        return response()->json($rootOrganizations);
    }

    private function loadOrganizationHierarchy($query)
    {
        $query->with(['childOrganizations' => function ($subQuery) {
            $this->loadOrganizationHierarchy($subQuery);
        }])->orderBy('name');
    }

    public function getByType(Request $request, string $type)
    {
        $validTypes = ['holding_company', 'subsidiary', 'division', 'branch', 'department', 'unit'];

        if (! in_array($type, $validTypes)) {
            return response()->json([
                'message' => 'Invalid organization type',
            ], 400);
        }

        $organizations = Organization::where('organization_type', $type)
            ->with(['parentOrganization', 'organizationUnits'])
            ->orderBy('name')
            ->get();

        return OrganizationResource::collection($organizations);
    }
}
