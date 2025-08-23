<?php

namespace App\Http\Controllers;

use App\Http\Requests\Organization\AddMemberRequest;
use App\Http\Requests\Organization\CreateRoleRequest;
use App\Http\Requests\Organization\StoreOrganizationRequest;
use App\Http\Requests\Organization\UpdateMemberRequest;
use App\Http\Requests\Organization\UpdateOrganizationRequest;
use App\Models\Auth\Role;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Services\ActivityLogService;
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
            ->withCount(['organizationUnits', 'childOrganizations', 'memberships'])
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

    public function store(StoreOrganizationRequest $request)
    {
        $validated = $request->validated();

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
            'parentOrganization',
            'childOrganizations',
            'organizationUnits.positions.activeMemberships.user',
            'memberships.user',
            'memberships.organizationPosition',
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

    public function update(UpdateOrganizationRequest $request, Organization $organization)
    {
        $validated = $request->validated();

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
            return redirect()->back()
                ->withErrors(['user_id' => 'User is already an active member of this organization']);
        }

        $validated['organization_id'] = $organization->id;
        $validated['status'] = 'active';
        $validated['created_by'] = Auth::id();
        $validated['updated_by'] = Auth::id();

        $membership = OrganizationMembership::create($validated);

        // Assign roles if provided
        if (! empty($validated['roles'])) {
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
    public function updateMember(UpdateMemberRequest $request, Organization $organization, OrganizationMembership $membership)
    {
        if ($membership->organization_id !== $organization->id) {
            abort(404, 'Membership not found in this organization');
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

        if (! empty($validated['permissions'])) {
            $role->givePermissionTo($validated['permissions']);
        }

        setPermissionsTeamId(null);

        return redirect()->back()
            ->with('success', 'Role created successfully');
    }
}
