<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreRoleRequest;
use App\Http\Requests\Api\UpdateRoleRequest;
use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response as ScribeResponse;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

#[Group('Role & Permission Management')]
class RoleController extends Controller
{
    #[Endpoint(
        title: 'Get roles',
        description: 'Retrieve a paginated list of roles with their permissions, optionally filtered by organization'
    )]
    #[Authenticated]
    #[QueryParam('organization_id', 'integer', 'Filter by organization/team ID', false, 1)]
    #[QueryParam('search', 'string', 'Search roles by name', false, 'admin')]
    #[QueryParam('per_page', 'integer', 'Number of results per page', false, 15)]
    #[QueryParam('sort', 'string', 'Sort field with optional - prefix for descending', false, 'name')]
    #[QueryParam('filter[name]', 'string', 'Filter by role name', false, 'admin')]
    #[QueryParam('filter[guard_name]', 'string', 'Filter by guard name', false, 'web')]
    #[QueryParam('filter[team_id]', 'string', 'Filter by team/organization ID', false, '1')]
    #[ScribeResponse([
        'data' => [
            ['id' => 1, 'name' => 'admin', 'permissions' => [['name' => 'org:admin']]],
            ['id' => 2, 'name' => 'member', 'permissions' => [['name' => 'org:read']]],
        ],
        'meta' => ['current_page' => 1, 'total' => 2],
    ])]
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 15);
        $perPage = in_array($perPage, [5, 10, 15, 25, 50, 100]) ? $perPage : 15;

        $roles = QueryBuilder::for(Role::class)
            ->allowedFilters([
                AllowedFilter::partial('name'),
                AllowedFilter::exact('guard_name'),
                AllowedFilter::exact('team_id'),
            ])
            ->allowedSorts([
                'name',
                'guard_name',
                'users_count',
                'created_at',
                'updated_at',
            ])
            ->defaultSort('name')
            ->with(['permissions', 'organization', 'updatedBy'])
            ->withCount(['users'])
            ->paginate($perPage)
            ->appends($request->query());

        return response()->json($roles);
    }

    #[Endpoint(
        title: 'Create role',
        description: 'Create a new role with specified permissions'
    )]
    #[Authenticated]
    #[ScribeResponse(['message' => 'Role created successfully', 'role' => ['id' => 1, 'name' => 'manager', 'permissions' => []]], 201)]
    #[ScribeResponse(['message' => 'Role already exists for this team/guard combination'], 400)]
    public function store(StoreRoleRequest $request)
    {
        $validated = $request->validated();

        // Check if role already exists for this team/guard
        $existingRole = Role::where([
            'name' => $validated['name'],
            'team_id' => $validated['team_id'] ?? null,
            'guard_name' => 'web',
        ])->first();

        if ($existingRole) {
            return response()->json([
                'message' => 'Role already exists'.($validated['team_id'] ? ' in this organization' : ' globally'),
            ], 400);
        }

        if ($validated['team_id']) {
            setPermissionsTeamId($validated['team_id']);
        }

        $role = Role::create([
            'name' => $validated['name'],
            'guard_name' => 'web',
            'team_id' => $validated['team_id'] ?? null,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        if (! empty($validated['permissions'])) {
            $role->givePermissionTo($validated['permissions']);
        }

        if ($validated['team_id']) {
            setPermissionsTeamId(null);
        }

        return response()->json([
            'message' => 'Role created successfully',
            'role' => $role->load('permissions'),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Role $role)
    {
        return response()->json($role->load('permissions', 'users'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRoleRequest $request, Role $role)
    {
        $validated = $request->validated();

        // Check if another role with same name exists for this team/guard
        $existingRole = Role::where([
            'name' => $validated['name'],
            'team_id' => $role->team_id,
            'guard_name' => 'web',
        ])->where('id', '!=', $role->id)->first();

        if ($existingRole) {
            return response()->json([
                'message' => 'Role name already exists'.($role->team_id ? ' in this organization' : ' globally'),
            ], 400);
        }

        if ($role->team_id) {
            setPermissionsTeamId($role->team_id);
        }

        $role->update([
            'name' => $validated['name'],
            'updated_by' => Auth::id(),
        ]);

        if (isset($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
        }

        if ($role->team_id) {
            setPermissionsTeamId(null);
        }

        return response()->json([
            'message' => 'Role updated successfully',
            'role' => $role->fresh()->load('permissions'),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Role $role)
    {
        // Check if role is assigned to users
        if ($role->users()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete role that is assigned to users',
            ], 400);
        }

        $role->delete();

        return response()->json(['message' => 'Role deleted successfully']);
    }

    /**
     * Get all available permissions
     */
    public function permissions()
    {
        $permissions = Permission::orderBy('name')->get(['id', 'name']);

        return response()->json($permissions);
    }
}
