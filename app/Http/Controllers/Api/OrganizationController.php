<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrganizationResource;
use App\Http\Resources\UserResource;
use App\Models\Organization;
use App\Models\User;
use App\Models\OrganizationMembership;
use App\Models\Auth\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

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

        $validated['created_by'] = Auth::id();
        $validated['updated_by'] = Auth::id();

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

        $validated['updated_by'] = Auth::id();

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

    /**
     * Get organization members
     */
    public function members(Organization $organization, Request $request)
    {
        $query = $organization->users()
            ->with(['roles' => function ($roleQuery) use ($organization) {
                $roleQuery->where('team_id', $organization->id);
            }]);

        if ($request->has('status')) {
            $query->wherePivot('status', $request->status);
        } else {
            $query->wherePivot('status', 'active');
        }

        $members = $query->paginate($request->get('per_page', 15));

        return UserResource::collection($members);
    }

    /**
     * Add member to organization
     */
    public function addMember(Request $request, Organization $organization)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:sys_users,id',
            'membership_type' => 'required|string|in:employee,contractor,board_member,executive',
            'organization_unit_id' => 'nullable|exists:organization_units,id',
            'organization_position_id' => 'nullable|exists:organization_positions,id',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
            'status' => 'required|in:active,inactive,terminated',
            'roles' => 'nullable|array',
            'roles.*' => 'string',
        ]);

        // Check if user is already a member
        $existingMembership = OrganizationMembership::where([
            'user_id' => $validated['user_id'],
            'organization_id' => $organization->id,
            'status' => 'active'
        ])->first();

        if ($existingMembership) {
            return response()->json([
                'message' => 'User is already an active member of this organization'
            ], 400);
        }

        $validated['organization_id'] = $organization->id;
        $validated['created_by'] = Auth::id() ?? 1; // Default for testing
        $validated['updated_by'] = Auth::id() ?? 1; // Default for testing

        $membership = OrganizationMembership::create($validated);

        // Assign roles if provided
        if (!empty($validated['roles'])) {
            $user = User::find($validated['user_id']);
            foreach ($validated['roles'] as $roleName) {
                $user->assignRoleInOrganization($roleName, $organization);
            }
        }

        return response()->json([
            'message' => 'Member added successfully',
            'membership' => $membership->load('user', 'organization')
        ], 201);
    }

    /**
     * Update organization membership
     */
    public function updateMember(Request $request, Organization $organization, OrganizationMembership $membership)
    {
        if ($membership->organization_id !== $organization->id) {
            return response()->json(['message' => 'Membership not found in this organization'], 404);
        }

        $validated = $request->validate([
            'membership_type' => 'required|string|in:employee,contractor,board_member,executive',
            'organization_unit_id' => 'nullable|exists:organization_units,id',
            'organization_position_id' => 'nullable|exists:organization_positions,id',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
            'status' => 'required|in:active,inactive,terminated',
            'roles' => 'nullable|array',
            'roles.*' => 'string',
        ]);

        $validated['updated_by'] = Auth::id();
        $membership->update($validated);

        // Update roles if provided
        if (isset($validated['roles'])) {
            $user = $membership->user;
            
            // Remove existing roles in this organization
            $existingRoles = $user->getRolesInOrganization($organization)->get();
            foreach ($existingRoles as $role) {
                $user->removeRoleFromOrganization($role->name, $organization);
            }
            
            // Assign new roles
            foreach ($validated['roles'] as $roleName) {
                $user->assignRoleInOrganization($roleName, $organization);
            }
        }

        return response()->json([
            'message' => 'Membership updated successfully',
            'membership' => $membership->fresh()->load('user', 'organization')
        ]);
    }

    /**
     * Remove member from organization
     */
    public function removeMember(Organization $organization, OrganizationMembership $membership)
    {
        if ($membership->organization_id !== $organization->id) {
            return response()->json(['message' => 'Membership not found in this organization'], 404);
        }

        $user = $membership->user;
        
        // Remove all roles in this organization
        $existingRoles = $user->getRolesInOrganization($organization)->get();
        foreach ($existingRoles as $role) {
            $user->removeRoleFromOrganization($role->name, $organization);
        }

        $membership->delete();

        return response()->json(['message' => 'Member removed successfully']);
    }

    /**
     * Get organization roles
     */
    public function roles(Organization $organization)
    {
        $roles = Role::where('team_id', $organization->id)
            ->with('permissions')
            ->get();

        return response()->json($roles);
    }

    /**
     * Create organization role
     */
    public function createRole(Request $request, Organization $organization)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|exists:sys_permissions,name',
        ]);

        // Check if role already exists for this organization
        $existingRole = Role::where([
            'name' => $validated['name'],
            'team_id' => $organization->id,
            'guard_name' => 'web'
        ])->first();

        if ($existingRole) {
            return response()->json([
                'message' => 'Role already exists in this organization'
            ], 400);
        }

        setPermissionsTeamId($organization->id);

        $role = Role::create([
            'name' => $validated['name'],
            'guard_name' => 'web',
            'team_id' => $organization->id,
            'created_by' => Auth::id() ?? 1, // Default for testing
            'updated_by' => Auth::id() ?? 1, // Default for testing
        ]);

        if (!empty($validated['permissions'])) {
            $role->givePermissionTo($validated['permissions']);
        }

        setPermissionsTeamId(null);

        return response()->json([
            'message' => 'Role created successfully',
            'role' => $role->load('permissions')
        ], 201);
    }
}
