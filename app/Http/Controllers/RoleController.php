<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\Organization;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class RoleController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:view roles')->only(['index', 'show']);
        $this->middleware('permission:create roles')->only(['create', 'store']);
        $this->middleware('permission:edit roles')->only(['edit', 'update']);
        $this->middleware('permission:delete roles')->only(['destroy']);
    }

    public function index(Request $request)
    {
        $query = Role::query()
            ->with(['permissions', 'users'])
            ->withCount('users');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->search.'%');
        }

        if ($request->filled('team_id')) {
            $query->where('team_id', $request->team_id);
        }

        $roles = $query->orderBy('name')->paginate(10);

        $organizations = Organization::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Roles/Index', [
            'roles' => $roles,
            'organizations' => $organizations,
            'filters' => $request->only(['search', 'team_id']),
        ]);
    }

    public function create()
    {
        $permissions = Permission::orderBy('name')->get();
        $organizations = Organization::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Roles/Create', [
            'permissions' => $permissions,
            'organizations' => $organizations,
        ]);
    }

    public function store(StoreRoleRequest $request)
    {
        $validated = $request->validated();

        $role = Role::create([
            'name' => $validated['name'],
            'guard_name' => 'web',
            'team_id' => $validated['team_id'] ?? null,
        ]);

        if (! empty($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
        }

        ActivityLogService::log('role', 'created', $role->id, [
            'role_name' => $role->name,
            'team_id' => $role->team_id,
            'permissions_count' => count($validated['permissions'] ?? []),
        ]);

        return redirect()->route('roles.index')
            ->with('success', 'Role created successfully.');
    }

    public function show(Role $role)
    {
        $role->load(['permissions', 'users']);

        return Inertia::render('Roles/Show', [
            'role' => $role,
        ]);
    }

    public function edit(Role $role)
    {
        $role->load('permissions');
        $permissions = Permission::orderBy('name')->get();
        $organizations = Organization::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Roles/Edit', [
            'role' => $role,
            'permissions' => $permissions,
            'organizations' => $organizations,
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role)
    {
        $validated = $request->validated();

        $role->update([
            'name' => $validated['name'],
        ]);

        if (array_key_exists('permissions', $validated)) {
            $role->syncPermissions($validated['permissions'] ?? []);
        }

        ActivityLogService::log('role', 'updated', $role->id, [
            'role_name' => $role->name,
            'permissions_count' => count($validated['permissions'] ?? []),
        ]);

        return redirect()->route('roles.index')
            ->with('success', 'Role updated successfully.');
    }

    public function destroy(Role $role)
    {
        $roleName = $role->name;

        if ($role->users()->count() > 0) {
            return redirect()->route('roles.index')
                ->with('error', 'Cannot delete role that is assigned to users.');
        }

        $role->delete();

        ActivityLogService::log('role', 'deleted', $role->id, [
            'role_name' => $roleName,
        ]);

        return redirect()->route('roles.index')
            ->with('success', 'Role deleted successfully.');
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'roles' => 'required|array',
            'roles.*' => 'string|exists:sys_roles,id',
        ]);

        $roleIds = $request->input('roles');

        DB::transaction(function () use ($roleIds) {
            $roles = Role::whereIn('id', $roleIds)->get();

            foreach ($roles as $role) {
                // Check if role has users
                if ($role->users()->count() > 0) {
                    continue; // Skip roles that have users assigned
                }

                ActivityLogService::log('role', 'bulk_deleted', $role->id, [
                    'role_name' => $role->name,
                ]);

                $role->delete();
            }
        });

        return redirect()->route('roles.index')
            ->with('success', 'Selected roles deleted successfully.');
    }

    public function bulkAssignPermissions(Request $request)
    {
        $request->validate([
            'roles' => 'required|array',
            'roles.*' => 'string|exists:sys_roles,id',
            'permissions' => 'required|array',
            'permissions.*' => 'string|exists:sys_permissions,name',
        ]);

        $roleIds = $request->input('roles');
        $permissionNames = $request->input('permissions');

        DB::transaction(function () use ($roleIds, $permissionNames) {
            $roles = Role::whereIn('id', $roleIds)->get();
            $permissions = Permission::whereIn('name', $permissionNames)->get();

            foreach ($roles as $role) {
                $role->syncPermissions($permissions);

                ActivityLogService::log('role', 'bulk_permissions_assigned', $role->id, [
                    'role_name' => $role->name,
                    'permissions_count' => count($permissionNames),
                ]);
            }
        });

        return redirect()->route('roles.index')
            ->with('success', 'Permissions assigned to selected roles successfully.');
    }
}
