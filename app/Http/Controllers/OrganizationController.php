<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\User;
use App\Models\OrganizationMembership;
use App\Models\Auth\Role;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class OrganizationController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:view organizations')->only(['index', 'show', 'hierarchy']);
        $this->middleware('permission:create organizations')->only(['create', 'store']);
        $this->middleware('permission:edit organizations')->only(['edit', 'update']);
        $this->middleware('permission:delete organizations')->only(['destroy']);
        $this->middleware('permission:view organization memberships')->only(['members']);
        $this->middleware('permission:create organization memberships')->only(['addMember']);
        $this->middleware('permission:edit organization memberships')->only(['updateMember']);
        $this->middleware('permission:delete organization memberships')->only(['removeMember']);
    }

    public function index()
    {
        $organizations = Organization::query()
            ->withCount(['departments', 'organizationUnits', 'childOrganizations'])
            ->with(['parentOrganization'])
            ->orderBy('level')
            ->orderBy('name')
            ->paginate(10);

        return Inertia::render('Organizations/Index', [
            'organizations' => $organizations,
        ]);
    }

    public function create()
    {
        $parentOrganizations = Organization::where('organization_type', '!=', 'unit')
            ->orderBy('name')
            ->get(['id', 'name', 'organization_type']);

        return Inertia::render('Organizations/Create', [
            'parentOrganizations' => $parentOrganizations,
        ]);
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
            'authorized_capital' => 'nullable|numeric|min:0',
            'paid_capital' => 'nullable|numeric|min:0',
            'establishment_date' => 'nullable|date',
            'legal_status' => 'nullable|string|max:100',
            'business_activities' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $organization = Organization::create($validated);

        if ($organization->parent_organization_id) {
            $organization->updatePath();
        }

        // Log organization creation
        ActivityLogService::logOrganization('created', 'Organization created: '.$organization->name, [
            'organization_type' => $organization->organization_type,
            'parent_organization_id' => $organization->parent_organization_id,
        ], $organization);

        return redirect()->route('organizations.index')
            ->with('success', 'Organization created successfully.');
    }

    public function show(Organization $organization)
    {
        $organization->load([
            'departments.jobPositions.jobLevel',
            'parentOrganization',
            'childOrganizations',
            'organizationUnits.positions.activeMemberships.user',
            'memberships.user.organizationPosition',
        ]);

        return Inertia::render('Organizations/Show', [
            'organization' => $organization,
        ]);
    }

    public function edit(Organization $organization)
    {
        $parentOrganizations = Organization::where('organization_type', '!=', 'unit')
            ->where('id', '!=', $organization->id)
            ->orderBy('name')
            ->get(['id', 'name', 'organization_type']);

        return Inertia::render('Organizations/Edit', [
            'organization' => $organization,
            'parentOrganizations' => $parentOrganizations,
        ]);
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
            'authorized_capital' => 'nullable|numeric|min:0',
            'paid_capital' => 'nullable|numeric|min:0',
            'establishment_date' => 'nullable|date',
            'legal_status' => 'nullable|string|max:100',
            'business_activities' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if (isset($validated['parent_organization_id']) && $validated['parent_organization_id'] == $organization->id) {
            return redirect()->back()
                ->withErrors(['parent_organization_id' => 'Organization cannot be its own parent']);
        }

        $oldParentId = $organization->parent_organization_id;
        $oldData = $organization->toArray();
        $organization->update($validated);

        if ($oldParentId !== $organization->parent_organization_id) {
            $organization->updatePath();
        }

        // Log organization update
        ActivityLogService::logOrganization('updated', 'Organization updated: '.$organization->name, [
            'old_parent_id' => $oldParentId,
            'new_parent_id' => $organization->parent_organization_id,
            'changes' => array_diff_assoc($validated, $oldData),
        ], $organization);

        return redirect()->route('organizations.index')
            ->with('success', 'Organization updated successfully.');
    }

    public function destroy(Organization $organization)
    {
        if ($organization->childOrganizations()->count() > 0) {
            return redirect()->back()
                ->withErrors(['organization' => 'Cannot delete organization with child organizations. Please reassign or delete child organizations first.']);
        }

        if ($organization->organizationUnits()->count() > 0) {
            return redirect()->back()
                ->withErrors(['organization' => 'Cannot delete organization with organizational units. Please delete units first.']);
        }

        // Log organization deletion before deleting
        ActivityLogService::logOrganization('deleted', 'Organization deleted: '.$organization->name, [
            'organization_type' => $organization->organization_type,
            'parent_organization_id' => $organization->parent_organization_id,
        ], $organization);

        $organization->delete();

        return redirect()->route('organizations.index')
            ->with('success', 'Organization deleted successfully.');
    }

    public function hierarchy()
    {
        $organizations = Organization::whereNull('parent_organization_id')
            ->with(['childOrganizations' => function ($query) {
                $this->loadHierarchy($query);
            }])
            ->orderBy('name')
            ->get();

        return Inertia::render('Organizations/Hierarchy', [
            'organizations' => $organizations,
        ]);
    }

    private function loadHierarchy($query)
    {
        $query->with(['childOrganizations' => function ($subQuery) {
            $this->loadHierarchy($subQuery);
        }])->orderBy('name');
    }

    /**
     * Show organization members
     */
    public function members(Organization $organization)
    {
        $members = $organization->users()
            ->with(['roles' => function ($roleQuery) use ($organization) {
                $roleQuery->where('team_id', $organization->id);
            }])
            ->wherePivot('status', 'active')
            ->paginate(15);

        $availableUsers = User::whereNotIn('id', $organization->users()->pluck('sys_users.id'))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $organizationRoles = Role::where('team_id', $organization->id)->get();

        return Inertia::render('Organizations/Members', [
            'organization' => $organization,
            'members' => $members,
            'availableUsers' => $availableUsers,
            'organizationRoles' => $organizationRoles,
        ]);
    }

    /**
     * Add member to organization
     */
    public function addMember(Request $request, Organization $organization)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:sys_users,id',
            'membership_type' => 'required|string|in:employee,contractor,board_member,executive',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
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
            return redirect()->back()
                ->withErrors(['user_id' => 'User is already an active member of this organization']);
        }

        $validated['organization_id'] = $organization->id;
        $validated['status'] = 'active';
        $validated['created_by'] = Auth::id();
        $validated['updated_by'] = Auth::id();

        $membership = OrganizationMembership::create($validated);

        // Assign roles if provided
        if (!empty($validated['roles'])) {
            $user = User::find($validated['user_id']);
            foreach ($validated['roles'] as $roleName) {
                $user->assignRoleInOrganization($roleName, $organization);
            }
        }

        return redirect()->back()
            ->with('success', 'Member added successfully');
    }

    /**
     * Update organization membership
     */
    public function updateMember(Request $request, Organization $organization, OrganizationMembership $membership)
    {
        if ($membership->organization_id !== $organization->id) {
            abort(404, 'Membership not found in this organization');
        }

        $validated = $request->validate([
            'membership_type' => 'required|string|in:employee,contractor,board_member,executive',
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

        return redirect()->back()
            ->with('success', 'Membership updated successfully');
    }

    /**
     * Remove member from organization
     */
    public function removeMember(Organization $organization, OrganizationMembership $membership)
    {
        if ($membership->organization_id !== $organization->id) {
            abort(404, 'Membership not found in this organization');
        }

        $user = $membership->user;
        
        // Remove all roles in this organization
        $existingRoles = $user->getRolesInOrganization($organization)->get();
        foreach ($existingRoles as $role) {
            $user->removeRoleFromOrganization($role->name, $organization);
        }

        $membership->delete();

        return redirect()->back()
            ->with('success', 'Member removed successfully');
    }

    /**
     * Show organization roles management
     */
    public function roles(Organization $organization)
    {
        $roles = Role::where('team_id', $organization->id)
            ->with('permissions')
            ->get();

        return Inertia::render('Organizations/Roles', [
            'organization' => $organization,
            'roles' => $roles,
        ]);
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
            return redirect()->back()
                ->withErrors(['name' => 'Role already exists in this organization']);
        }

        setPermissionsTeamId($organization->id);

        $role = Role::create([
            'name' => $validated['name'],
            'guard_name' => 'web',
            'team_id' => $organization->id,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        if (!empty($validated['permissions'])) {
            $role->givePermissionTo($validated['permissions']);
        }

        setPermissionsTeamId(null);

        return redirect()->back()
            ->with('success', 'Role created successfully');
    }
}
