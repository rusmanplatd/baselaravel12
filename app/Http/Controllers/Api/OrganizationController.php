<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrganizationResource;
use App\Http\Resources\UserResource;
use App\Models\Auth\Role;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response as ScribeResponse;

#[Group('Organization Management')]
class OrganizationController extends Controller
{
    #[Endpoint(
        title: 'Get organizations',
        description: 'Retrieve a paginated list of organizations with optional filtering and relationships'
    )]
    #[Authenticated]
    #[QueryParam('organization_type', 'string', 'Filter by organization type', false, 'holding_company')]
    #[QueryParam('parent_organization_id', 'integer', 'Filter by parent organization ID', false, 1)]
    #[QueryParam('hierarchy_root', 'boolean', 'Show only root organizations (no parent)', false, true)]
    #[QueryParam('include', 'string', 'Include relationships (comma-separated: departments,parent,children,units)', false, 'departments,children')]
    #[QueryParam('per_page', 'integer', 'Number of results per page', false, 15)]
    #[ScribeResponse(['data' => ['id' => 1, 'name' => 'Acme Corp', 'organization_type' => 'holding_company'], 'meta' => ['current_page' => 1]])]
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

    #[Endpoint(
        title: 'Create organization',
        description: 'Create a new organization with hierarchical structure support'
    )]
    #[Authenticated]
    #[ScribeResponse(['id' => 1, 'name' => 'New Organization', 'organization_type' => 'division', 'created_at' => '2024-01-15T10:30:00Z'], 201)]
    #[ScribeResponse(['message' => 'Organization cannot be its own parent'], 400)]
    public function store(StoreOrganizationRequest $request)
    {
        $validated = $request->validated();

        $validated['created_by'] = Auth::id();
        $validated['updated_by'] = Auth::id();

        $organization = Organization::create($validated);

        if ($organization->parent_organization_id) {
            $organization->updatePath();
        }

        return new OrganizationResource($organization->load(['parentOrganization', 'childOrganizations']));
    }

    #[Endpoint(
        title: 'Get organization details',
        description: 'Retrieve detailed information about a specific organization'
    )]
    #[Authenticated]
    #[QueryParam('include', 'string', 'Include relationships (comma-separated: departments)', false, 'departments')]
    #[ScribeResponse(['id' => 1, 'name' => 'Acme Corp', 'organization_type' => 'holding_company'])]
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

    #[Endpoint(
        title: 'Update organization',
        description: 'Update an existing organization with validation to prevent circular hierarchies'
    )]
    #[Authenticated]
    #[ScribeResponse(['id' => 1, 'name' => 'Updated Organization', 'organization_type' => 'division', 'updated_at' => '2024-01-15T10:30:00Z'], 200)]
    #[ScribeResponse(['message' => 'Organization cannot be its own parent'], 400)]
    public function update(UpdateOrganizationRequest $request, Organization $organization)
    {
        $validated = $request->validated();

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

    #[Endpoint(
        title: 'Delete organization',
        description: 'Delete an organization after validating no dependencies exist'
    )]
    #[Authenticated]
    #[ScribeResponse(['message' => 'Organization deleted successfully'], 200)]
    #[ScribeResponse(['message' => 'Cannot delete organization with child organizations. Please reassign or delete child organizations first.'], 400)]
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

    #[Endpoint(
        title: 'Get organization hierarchy',
        description: 'Retrieve the complete organizational hierarchy tree starting from root organizations'
    )]
    #[Authenticated]
    #[ScribeResponse([
        ['id' => 1, 'name' => 'Root Corp', 'children' => [
            ['id' => 2, 'name' => 'Division A', 'children' => []],
        ]],
    ])]
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

    #[Endpoint(
        title: 'Get organizations by type',
        description: 'Retrieve organizations filtered by their organizational type'
    )]
    #[Authenticated]
    #[QueryParam('type', 'string', 'Organization type', true, 'holding_company', enum: ['holding_company', 'subsidiary', 'division', 'branch', 'department', 'unit'])]
    #[ScribeResponse(['data' => [['id' => 1, 'name' => 'Acme Holdings', 'organization_type' => 'holding_company']]])]
    #[ScribeResponse(['message' => 'Invalid organization type'], 400)]
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

    #[Endpoint(
        title: 'Get organization members',
        description: 'Retrieve a paginated list of members in an organization with their roles'
    )]
    #[Authenticated]
    #[QueryParam('status', 'string', 'Filter by membership status', false, 'active')]
    #[QueryParam('per_page', 'integer', 'Number of results per page', false, 15)]
    #[ScribeResponse(['data' => ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com'], 'meta' => ['current_page' => 1]])]
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

    #[Endpoint(
        title: 'Add member to organization',
        description: 'Add a user as a member to an organization with optional role assignments'
    )]
    #[Authenticated]
    #[ScribeResponse(['message' => 'Member added successfully', 'membership' => ['id' => 1, 'user_id' => 1, 'organization_id' => 1]], 201)]
    #[ScribeResponse(['message' => 'User is already an active member of this organization'], 400)]
    public function addMember(AddMemberRequest $request, Organization $organization)
    {
        $validated = $request->validated();

        // Check if user is already a member
        $existingMembership = OrganizationMembership::where([
            'user_id' => $validated['user_id'],
            'organization_id' => $organization->id,
            'status' => 'active',
        ])->first();

        if ($existingMembership) {
            return response()->json([
                'message' => 'User is already an active member of this organization',
            ], 400);
        }

        $validated['organization_id'] = $organization->id;
        $validated['created_by'] = Auth::id() ?? 1; // Default for testing
        $validated['updated_by'] = Auth::id() ?? 1; // Default for testing

        $membership = OrganizationMembership::create($validated);

        // Assign roles if provided
        if (! empty($validated['roles'])) {
            $user = User::find($validated['user_id']);
            foreach ($validated['roles'] as $roleName) {
                $user->assignRoleInOrganization($roleName, $organization);
            }
        }

        return response()->json([
            'message' => 'Member added successfully',
            'membership' => $membership->load('user', 'organization'),
        ], 201);
    }

    #[Endpoint(
        title: 'Update organization membership',
        description: 'Update an existing organization membership including role assignments'
    )]
    #[Authenticated]
    #[ScribeResponse(['message' => 'Membership updated successfully', 'membership' => ['id' => 1, 'status' => 'active']], 200)]
    #[ScribeResponse(['message' => 'Membership not found in this organization'], 404)]
    public function updateMember(UpdateMemberRequest $request, Organization $organization, OrganizationMembership $membership)
    {
        if ($membership->organization_id !== $organization->id) {
            return response()->json(['message' => 'Membership not found in this organization'], 404);
        }

        $validated = $request->validated();

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
            'membership' => $membership->fresh()->load('user', 'organization'),
        ]);
    }

    #[Endpoint(
        title: 'Remove member from organization',
        description: 'Remove a member from an organization and revoke all associated roles'
    )]
    #[Authenticated]
    #[ScribeResponse(['message' => 'Member removed successfully'], 200)]
    #[ScribeResponse(['message' => 'Membership not found in this organization'], 404)]
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

    #[Endpoint(
        title: 'Get organization roles',
        description: 'Retrieve all roles defined for a specific organization'
    )]
    #[Authenticated]
    #[ScribeResponse([
        ['id' => 1, 'name' => 'admin', 'permissions' => [['name' => 'organization:admin']]],
        ['id' => 2, 'name' => 'member', 'permissions' => [['name' => 'organization:read']]],
    ])]
    public function roles(Organization $organization)
    {
        $roles = Role::where('team_id', $organization->id)
            ->with('permissions')
            ->get();

        return response()->json($roles);
    }

    #[Endpoint(
        title: 'Create organization role',
        description: 'Create a new role within an organization with specific permissions'
    )]
    #[Authenticated]
    #[ScribeResponse(['message' => 'Role created successfully', 'role' => ['id' => 1, 'name' => 'manager', 'permissions' => []]], 201)]
    #[ScribeResponse(['message' => 'Role already exists in this organization'], 400)]
    public function createRole(CreateRoleRequest $request, Organization $organization)
    {
        $validated = $request->validated();

        // Check if role already exists for this organization
        $existingRole = Role::where([
            'name' => $validated['name'],
            'team_id' => $organization->id,
            'guard_name' => 'web',
        ])->first();

        if ($existingRole) {
            return response()->json([
                'message' => 'Role already exists in this organization',
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

        if (! empty($validated['permissions'])) {
            $role->givePermissionTo($validated['permissions']);
        }

        setPermissionsTeamId(null);

        return response()->json([
            'message' => 'Role created successfully',
            'role' => $role->load('permissions'),
        ], 201);
    }
}
