<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePermissionRequest;
use App\Http\Requests\UpdatePermissionRequest;
use App\Models\Auth\Permission;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PermissionController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:view permissions')->only(['index', 'show']);
        $this->middleware('permission:create permissions')->only(['create', 'store']);
        $this->middleware('permission:edit permissions')->only(['edit', 'update']);
        $this->middleware('permission:delete permissions')->only(['destroy']);
    }

    public function index(Request $request)
    {
        $query = Permission::query()
            ->with(['roles'])
            ->withCount('roles');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->search.'%');
        }

        if ($request->filled('guard_name')) {
            $query->where('guard_name', $request->guard_name);
        }

        $permissions = $query->orderBy('name')->paginate(10);

        $guardNames = Permission::distinct('guard_name')
            ->orderBy('guard_name')
            ->pluck('guard_name')
            ->filter();

        return Inertia::render('Permissions/Index', [
            'permissions' => $permissions,
            'guardNames' => $guardNames,
            'filters' => $request->only(['search', 'guard_name']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Permissions/Create');
    }

    public function store(StorePermissionRequest $request)
    {
        $validated = $request->validated();

        $permission = Permission::create([
            'name' => $validated['name'],
            'guard_name' => $validated['guard_name'] ?? 'web',
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        ActivityLogService::logSystem('created', "Permission '{$permission->name}' created", [
            'permission_name' => $permission->name,
            'guard_name' => $permission->guard_name,
        ], $permission);

        return redirect()->route('permissions.index')
            ->with('success', 'Permission created successfully.');
    }

    public function show(Permission $permission)
    {
        $permission->load(['roles']);

        return Inertia::render('Permissions/Show', [
            'permission' => $permission,
        ]);
    }

    public function edit(Permission $permission)
    {
        return Inertia::render('Permissions/Edit', [
            'permission' => $permission,
        ]);
    }

    public function update(UpdatePermissionRequest $request, Permission $permission)
    {
        $validated = $request->validated();

        $permission->update([
            'name' => $validated['name'],
            'guard_name' => $validated['guard_name'] ?? $permission->guard_name,
            'updated_by' => auth()->id(),
        ]);

        ActivityLogService::logSystem('updated', "Permission '{$permission->name}' updated", [
            'permission_name' => $permission->name,
            'guard_name' => $permission->guard_name,
        ], $permission);

        return redirect()->route('permissions.index')
            ->with('success', 'Permission updated successfully.');
    }

    public function destroy(Permission $permission)
    {
        $permissionName = $permission->name;

        if ($permission->roles()->count() > 0) {
            return redirect()->route('permissions.index')
                ->with('error', 'Cannot delete permission that is assigned to roles.');
        }

        $permission->delete();

        ActivityLogService::logSystem('deleted', "Permission '{$permissionName}' deleted", [
            'permission_name' => $permissionName,
        ]);

        return redirect()->route('permissions.index')
            ->with('success', 'Permission deleted successfully.');
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'string|exists:sys_permissions,id',
        ]);

        $permissionIds = $request->input('permissions');

        DB::transaction(function () use ($permissionIds) {
            $permissions = Permission::whereIn('id', $permissionIds)->get();

            foreach ($permissions as $permission) {
                // Check if permission is assigned to roles
                if ($permission->roles()->count() > 0) {
                    continue; // Skip permissions that are assigned to roles
                }

                ActivityLogService::logSystem('bulk_deleted', "Permission '{$permission->name}' bulk deleted", [
                    'permission_name' => $permission->name,
                ], $permission);

                $permission->delete();
            }
        });

        return redirect()->route('permissions.index')
            ->with('success', 'Selected permissions deleted successfully.');
    }

    public function bulkCreateByPattern(Request $request)
    {
        $request->validate([
            'resources' => 'required|array',
            'resources.*' => 'string|max:255',
            'actions' => 'required|array',
            'actions.*' => 'string|max:255',
            'guard_name' => 'nullable|string|max:255',
        ]);

        $resources = $request->input('resources');
        $actions = $request->input('actions');
        $guardName = $request->input('guard_name', 'web');

        $createdCount = 0;

        DB::transaction(function () use ($resources, $actions, $guardName, &$createdCount) {
            foreach ($resources as $resource) {
                foreach ($actions as $action) {
                    $permissionName = "{$action} {$resource}";

                    $permission = Permission::firstOrCreate([
                        'name' => $permissionName,
                        'guard_name' => $guardName,
                    ]);

                    if ($permission->wasRecentlyCreated) {
                        $createdCount++;

                        ActivityLogService::logSystem('bulk_created', "Permission '{$permission->name}' bulk created", [
                            'permission_name' => $permission->name,
                            'guard_name' => $permission->guard_name,
                        ], $permission);
                    }
                }
            }
        });

        return redirect()->route('permissions.index')
            ->with('success', "Created {$createdCount} new permissions successfully.");
    }
}
